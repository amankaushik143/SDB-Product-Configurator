<?php
/**
 * [sdb_product_range] shortcode — a row of category icons above a
 * paginated WooCommerce product grid. Clicking an icon or a page number
 * refreshes the grid via AJAX (see class-sdb-range-ajax.php).
 *
 * Front-end markup/CSS/JS is entirely namespaced under "sdbpr-" and is
 * independent from the configurator modal's "sdbpkp-"/"sdbpc-" namespace,
 * so the two features never clash on the same page.
 *
 * @package SDB_Product_Configurator
 */

defined( 'ABSPATH' ) || exit;

class SDB_Product_Range {

	/** @var int Counter so multiple shortcode instances on one page get unique DOM ids. */
	private static $instance_count = 0;

	public static function init() {
		add_shortcode( 'sdb_product_range', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function enqueue_assets() {
		// TEMP (dev only): bust the browser cache on every single page load so CSS/JS
		// tweaks show up immediately without a hard refresh. Swap `time()` back to
		// `SDBPC_VERSION` once styling is finalized — time()-based versioning defeats
		// normal browser caching and shouldn't ship to production.
		$sdbpr_dev_version = time();

		wp_register_style(
			'sdbpr-style',
			SDBPC_PLUGIN_URL . 'assets/css/product-range.css',
			array(),
			$sdbpr_dev_version
		);

		wp_register_script(
			'sdbpr-script',
			SDBPC_PLUGIN_URL . 'assets/js/product-range.js',
			array( 'jquery' ),
			$sdbpr_dev_version,
			true
		);

		wp_localize_script(
			'sdbpr-script',
			'sdbprConfig',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'cartUrl' => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
				'nonce'   => wp_create_nonce( 'sdbpr_nonce' ),
				'i18n'    => array(
					'noProducts'   => __( 'No products found in this category.', 'sdb-product-configurator' ),
					'errorLoading' => __( 'Something went wrong loading products. Please try again.', 'sdb-product-configurator' ),
					'adding'       => __( 'Adding…', 'sdb-product-configurator' ),
					'added'        => __( 'Added ✓', 'sdb-product-configurator' ),
					'viewCart'     => __( 'View cart', 'sdb-product-configurator' ),
					'errorCart'    => __( 'Could not add to cart. Please try again.', 'sdb-product-configurator' ),
					'outOfStock'   => __( 'Out of stock', 'sdb-product-configurator' ),
				),
			)
		);

		// Enqueued unconditionally (same approach the existing configurator
		// assets already use) so the shortcode still works from widgets,
		// template parts, or page-builder content that isn't in $post->post_content.
		wp_enqueue_style( 'sdbpr-style' );
		wp_enqueue_script( 'sdbpr-script' );
	}

	public static function render_shortcode( $atts ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'per_page' => '',
				'default'  => '', // product_cat slug or term_id override
			),
			$atts,
			'sdb_product_range'
		);

		$ranges = SDB_Range_Settings::get_ranges();

		// Only show icons for categories that currently have at least one product.
		$ranges = array_values(
			array_filter(
				$ranges,
				function ( $row ) {
					if ( empty( $row['term_id'] ) ) {
						return false;
					}
					$term = get_term( absint( $row['term_id'] ), 'product_cat' );
					return $term && ! is_wp_error( $term ) && $term->count > 0;
				}
			)
		);

		if ( empty( $ranges ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<p class="sdbpr-admin-hint">' .
					esc_html__( 'Product Range: no category icons with products to show yet. Go to Configurator → Product Range in the admin menu to add some, or add products to the configured categories.', 'sdb-product-configurator' ) .
					'</p>';
			}
			return '';
		}

		$per_page = $atts['per_page'] !== '' ? max( 1, absint( $atts['per_page'] ) ) : SDB_Range_Settings::get_per_page();

		$range_term_ids = wp_list_pluck( $ranges, 'term_id' );

		$default_term_id = 0;
		if ( '' !== $atts['default'] ) {
			$term = is_numeric( $atts['default'] )
				? get_term( absint( $atts['default'] ), 'product_cat' )
				: get_term_by( 'slug', sanitize_title( $atts['default'] ), 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$default_term_id = (int) $term->term_id;
			}
		}
		if ( ! $default_term_id || ! in_array( (int) $default_term_id, array_map( 'absint', $range_term_ids ), true ) ) {
			$configured_default = SDB_Range_Settings::get_default_term_id();
			$default_term_id    = in_array( (int) $configured_default, array_map( 'absint', $range_term_ids ), true )
				? $configured_default
				: absint( $ranges[0]['term_id'] );
		}

		self::$instance_count++;
		$instance_id = 'sdbpr-' . self::$instance_count . '-' . substr( md5( wp_json_encode( $atts ) . self::$instance_count ), 0, 6 );

		$initial = $default_term_id ? SDB_Range_Ajax::query_products( $default_term_id, 1, $per_page ) : null;

		ob_start();
		include SDBPC_PLUGIN_DIR . 'templates/product-range.php';
		return ob_get_clean();
	}

	/* ── Product card / pagination markup ────────────────────────────────
	 *    Used for the first, server-rendered page. The JS build for
	 *    later AJAX page/category switches mirrors these same class
	 *    names — keep the two in sync if you change either one. ────────── */

	public static function render_product_card_html( $product ) {
		$oos = empty( $product['in_stock'] ) || empty( $product['purchasable'] );
		ob_start();
		?>
		<div class="sdbpr-card<?php echo $oos ? ' sdbpr-card--oos' : ''; ?>" data-product-id="<?php echo esc_attr( $product['id'] ); ?>">
			<a class="sdbpr-card-image" href="<?php echo esc_url( $product['permalink'] ); ?>">
				<img src="<?php echo esc_url( $product['image'] ); ?>" alt="<?php echo esc_attr( $product['name'] ); ?>" loading="lazy">
			</a>
			<div class="sdbpr-card-body">
				<a class="sdbpr-card-name" href="<?php echo esc_url( $product['permalink'] ); ?>">
					<span class="sdbpr-card-name-text"><?php echo esc_html( $product['name'] ); ?></span>
					<span class="sdbpr-card-name-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="11"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg></span>
				</a>
				<div class="sdbpr-card-price"><?php echo wp_kses_post( $product['price_html'] ); ?></div>
			</div>
			<?php if ( $oos ) : ?>
				<div class="sdbpr-card-oos"><?php esc_html_e( 'Out of stock', 'sdb-product-configurator' ); ?></div>
			<?php else : ?>
				<div class="sdbpr-card-actions">
					<input type="number" class="sdbpr-qty-input" value="1" min="1" data-product-id="<?php echo esc_attr( $product['id'] ); ?>">
					<button type="button" class="sdbpr-add-btn" data-product-id="<?php echo esc_attr( $product['id'] ); ?>" aria-label="<?php esc_attr_e( 'Add to cart', 'sdb-product-configurator' ); ?>">
						<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
					</button>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function render_pagination_html( $current, $max ) {
		if ( $max <= 1 ) {
			return '';
		}
		ob_start();
		?>
		<div class="sdbpr-pagination">
			<button type="button" class="sdbpr-page-btn sdbpr-page-prev" data-page="<?php echo esc_attr( max( 1, $current - 1 ) ); ?>" <?php disabled( 1 === $current ); ?> aria-label="<?php esc_attr_e( 'Previous page', 'sdb-product-configurator' ); ?>">&lsaquo;</button>
			<?php for ( $p = 1; $p <= $max; $p++ ) : ?>
				<button type="button" class="sdbpr-page-btn<?php echo $p === $current ? ' sdbpr-page-active' : ''; ?>" data-page="<?php echo esc_attr( $p ); ?>"><?php echo esc_html( $p ); ?></button>
			<?php endfor; ?>
			<button type="button" class="sdbpr-page-btn sdbpr-page-next" data-page="<?php echo esc_attr( min( $max, $current + 1 ) ); ?>" <?php disabled( $current === $max ); ?> aria-label="<?php esc_attr_e( 'Next page', 'sdb-product-configurator' ); ?>">&rsaquo;</button>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ── Small render helpers shared by the template + kept here so the
	 *    template file stays purely presentational. ──────────────────────── */

	public static function resolve_icon_url( $row ) {
		if ( ! empty( $row['icon_id'] ) ) {
			$url = wp_get_attachment_image_url( absint( $row['icon_id'] ), 'thumbnail' );
			if ( $url ) {
				return $url;
			}
		}

		// Fall back to the WooCommerce category's own thumbnail, if any.
		if ( ! empty( $row['term_id'] ) ) {
			$thumb_id = get_term_meta( absint( $row['term_id'] ), 'thumbnail_id', true );
			if ( $thumb_id ) {
				$url = wp_get_attachment_image_url( absint( $thumb_id ), 'thumbnail' );
				if ( $url ) {
					return $url;
				}
			}
		}

		return '';
	}

	public static function resolve_label( $row ) {
		if ( ! empty( $row['label'] ) ) {
			return $row['label'];
		}

		if ( ! empty( $row['term_id'] ) ) {
			$term = get_term( absint( $row['term_id'] ), 'product_cat' );
			return ( $term && ! is_wp_error( $term ) ) ? $term->name : '';
		}

		return '';
	}
}
