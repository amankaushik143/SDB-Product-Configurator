<?php
/**
 * AJAX handlers for the "Product Range" shortcode – category-filtered,
 * paginated product grid + a single-click add-to-cart.
 *
 * Completely separate action names / nonce from the existing configurator
 * modal (SDB_Configurator_Ajax) so the two features never interfere.
 *
 * @package SDB_Product_Configurator
 */

defined( 'ABSPATH' ) || exit;

class SDB_Range_Ajax {

	public static function init() {
		add_action( 'wp_ajax_sdbpr_get_products', array( __CLASS__, 'get_products' ) );
		add_action( 'wp_ajax_nopriv_sdbpr_get_products', array( __CLASS__, 'get_products' ) );

		add_action( 'wp_ajax_sdbpr_add_to_cart', array( __CLASS__, 'add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_sdbpr_add_to_cart', array( __CLASS__, 'add_to_cart' ) );

		add_action( 'wp_ajax_sdbpr_quick_view', array( __CLASS__, 'quick_view' ) );
		add_action( 'wp_ajax_nopriv_sdbpr_quick_view', array( __CLASS__, 'quick_view' ) );
	}

	/* ── Get products for one category page ─────────────────────────────── */
	public static function get_products() {
		check_ajax_referer( 'sdbpr_nonce', 'nonce' );

		$term_id  = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$paged    = isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : SDB_Range_Settings::get_per_page();

		if ( ! $term_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing category.', 'sdb-product-configurator' ) ) );
		}

		$term = get_term( $term_id, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => __( 'Category not found.', 'sdb-product-configurator' ) ) );
		}

		wp_send_json_success( self::query_products( $term_id, $paged, $per_page ) );
	}

	/**
	 * Shared query — used by the AJAX handler above and by the shortcode's
	 * first, server-rendered page (SDB_Product_Range::render_shortcode).
	 */
	public static function query_products( $term_id, $paged = 1, $per_page = 8 ) {
		$per_page = $per_page > 0 ? $per_page : 8;

		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'paged'          => $paged,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy'         => 'product_cat',
						'field'            => 'term_id',
						'terms'            => array( $term_id ),
						'include_children' => true,
					),
				),
			)
		);

		$products = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$wc_product = wc_get_product( get_the_ID() );
				if ( ! $wc_product ) {
					continue;
				}

				$thumb_url = get_the_post_thumbnail_url( get_the_ID(), 'medium' );
				if ( ! $thumb_url ) {
					$thumb_url = wc_placeholder_img_src( 'medium' );
				}

				$products[] = array(
					'id'          => $wc_product->get_id(),
					'name'        => $wc_product->get_name(),
					'price_html'  => $wc_product->get_price_html(),
					'image'       => esc_url( $thumb_url ),
					'permalink'   => esc_url( get_permalink( $wc_product->get_id() ) ),
					'in_stock'    => $wc_product->is_in_stock(),
					'purchasable' => $wc_product->is_purchasable(),
				);
			}
			wp_reset_postdata();
		}

		$term = get_term( $term_id, 'product_cat' );

		return array(
			'products'     => $products,
			'term_id'      => $term_id,
			'term_name'    => ( $term && ! is_wp_error( $term ) ) ? $term->name : '',
			'current_page' => $paged,
			'max_pages'    => max( 1, (int) $query->max_num_pages ),
			'total'        => (int) $query->found_posts,
		);
	}

	/* ── Add a single product straight to the WooCommerce cart ──────────── */
	public static function add_to_cart() {
		check_ajax_referer( 'sdbpr_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$quantity   = isset( $_POST['quantity'] ) ? max( 1, absint( $_POST['quantity'] ) ) : 1;

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'No product specified.', 'sdb-product-configurator' ) ) );
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'Cart is not available.', 'sdb-product-configurator' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			wp_send_json_error( array( 'message' => __( 'This product is not available.', 'sdb-product-configurator' ) ) );
		}

		$added = WC()->cart->add_to_cart( $product_id, $quantity );

		if ( ! $added ) {
			wp_send_json_error( array( 'message' => __( 'Could not add this product to the cart.', 'sdb-product-configurator' ) ) );
		}

		// Standard WooCommerce fragments so any theme mini-cart updates too.
		$fragments = apply_filters( 'woocommerce_add_to_cart_fragments', array() );

		wp_send_json_success(
			array(
				'fragments'  => $fragments,
				'cart_count' => WC()->cart->get_cart_contents_count(),
				'cart_url'   => wc_get_cart_url(),
			)
		);
	}

	/* ── Quick view: fuller detail for one product, shown in a popup ─────── */
	public static function quick_view() {
		check_ajax_referer( 'sdbpr_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'No product specified.', 'sdb-product-configurator' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'sdb-product-configurator' ) ) );
		}

		$image_url = get_the_post_thumbnail_url( $product_id, 'large' );
		if ( ! $image_url ) {
			$image_url = wc_placeholder_img_src( 'large' );
		}

		$short_description = $product->get_short_description();
		if ( '' === $short_description ) {
			$short_description = wp_trim_words( $product->get_description(), 40 );
		}

		wp_send_json_success(
			array(
				'id'                => $product->get_id(),
				'name'              => $product->get_name(),
				'price_html'        => $product->get_price_html(),
				'image'             => esc_url( $image_url ),
				'permalink'         => esc_url( get_permalink( $product_id ) ),
				'short_description' => wp_kses_post( $short_description ),
				'in_stock'          => $product->is_in_stock(),
				'purchasable'       => $product->is_purchasable(),
			)
		);
	}
}
