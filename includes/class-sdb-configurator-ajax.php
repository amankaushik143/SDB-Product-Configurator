<?php
/**
 * AJAX handler – products, booking product fetch, add to cart with booking meta.
 *
 * @package SDB_Product_Configurator
 */

defined( 'ABSPATH' ) || exit;

class SDB_Configurator_Ajax {

    public static function init() {
        add_action( 'wp_ajax_sdbpc_get_products',        array( __CLASS__, 'get_products' ) );
        add_action( 'wp_ajax_nopriv_sdbpc_get_products', array( __CLASS__, 'get_products' ) );

        add_action( 'wp_ajax_sdbpc_get_booking_product',        array( __CLASS__, 'get_booking_product' ) );
        add_action( 'wp_ajax_nopriv_sdbpc_get_booking_product', array( __CLASS__, 'get_booking_product' ) );

        add_action( 'wp_ajax_sdbpc_add_to_cart',        array( __CLASS__, 'add_to_cart' ) );
        add_action( 'wp_ajax_nopriv_sdbpc_add_to_cart', array( __CLASS__, 'add_to_cart' ) );

        // Show booking meta on cart & checkout
        add_filter( 'woocommerce_get_item_data',               array( __CLASS__, 'display_booking_meta_cart' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'save_booking_meta_order' ), 10, 3 );
    }

    /* ── Get products for a step ──────────────────────────────────────────── */
    public static function get_products() {
        check_ajax_referer( 'sdbpc_nonce', 'nonce' );

         $prefix = isset( $_POST['prefix'] ) ? sanitize_text_field( $_POST['prefix'] ) : '';
         $step   = isset( $_POST['step'] )   ? sanitize_text_field( $_POST['step'] )   : '';

        if ( ! $prefix || ! $step ) {
            wp_send_json_error( array( 'message' => 'Missing parameters.' ) );
        }

        $tag_slug = strtolower( $prefix . '-' . $step );

       $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,

            'orderby'        => 'menu_order',
            'order'          => 'ASC',

            'tax_query'      => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => array($tag_slug),
                    'include_children' => true,
                ),
            ),
        );

        $query = new WP_Query($args);

    

        $term  = get_term_by( 'slug', $tag_slug, 'product_cat' );
        $cat_description = $term ? wpautop( wp_kses_post( $term->description ) ) : '';

        $products = array();

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $wc_product = wc_get_product( get_the_ID() );
                if ( ! $wc_product ) continue;

                $thumb_url = get_the_post_thumbnail_url( get_the_ID(), 'thumbnail' );
                if ( ! $thumb_url ) $thumb_url = wc_placeholder_img_src( 'thumbnail' );

                $qty_default_value = get_post_meta( $wc_product->get_id(), $prefix . '_qty_value', true );

                $products[] = array(
                    'id'          => $wc_product->get_id(),
                    'name'        => $wc_product->get_name(),
                    'price'       => (float) $wc_product->get_price(),
                    'description' => wp_strip_all_tags( $wc_product->get_description() ),
                    'image'       => esc_url( $thumb_url ),
                    'stock'       => $wc_product->is_in_stock(),
                    'default_qty' => (int) $qty_default_value,
                );
            }
            wp_reset_postdata();
        }

        wp_send_json_success( array(
            'products'        => $products,
            'cat_description' => $cat_description,
        ) );
    }

    /* ── Get single booking product info ─────────────────────────────────── */
    public static function get_booking_product() {
        check_ajax_referer( 'sdbpc_nonce', 'nonce' );

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => 'No product ID.' ) );
        }

        $wc_product = wc_get_product( $product_id );
        if ( ! $wc_product ) {
            wp_send_json_error( array( 'message' => 'Product not found.' ) );
        }

        wp_send_json_success( array(
            'product' => array(
                'id'    => $wc_product->get_id(),
                'name'  => $wc_product->get_name(),
                'price' => (float) $wc_product->get_price(),
            ),
        ) );
    }

    /* ── Add to cart (with booking meta support) ─────────────────────────── */
    public static function add_to_cart() {
        check_ajax_referer( 'sdbpc_nonce', 'nonce' );

        $raw_items = isset( $_POST['items'] ) ? $_POST['items'] : '';
        $items     = json_decode( stripslashes( $raw_items ), true );

        if ( ! is_array( $items ) || empty( $items ) ) {
            wp_send_json_error( array( 'message' => 'No items provided.' ) );
        }

        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            wp_send_json_error( array( 'message' => 'WooCommerce cart not available.' ) );
        }

        $added  = 0;
        $errors = array();

        foreach ( $items as $item ) {
            $product_id    = isset( $item['id'] )           ? absint( $item['id'] )                         : 0;
            $quantity      = isset( $item['quantity'] )      ? absint( $item['quantity'] )                   : 1;
            $is_booking    = isset( $item['isBooking'] )     ? (bool) $item['isBooking']                     : false;
            $date_only     = isset( $item['dateOnly'] )      ? (bool) $item['dateOnly']                      : false;
            $booking_start = isset( $item['bookingStart'] )  ? sanitize_text_field( $item['bookingStart'] )  : '';
            $booking_end   = isset( $item['bookingEnd'] )    ? sanitize_text_field( $item['bookingEnd'] )    : '';

            // Date-only booking: just set rentman session, no product to add
            if ( $is_booking && $date_only ) {
                if ( WC()->session && $booking_start ) {
                    WC()->session->set( 'rentman_rental_period_start', strtotime( $booking_start ) );
                    WC()->session->set( 'rentman_rental_period_end',   strtotime( $booking_end ?: $booking_start ) );
                    $added++;
                }
                continue;
            }

            if ( ! $product_id || ! $quantity ) continue;

            $product = wc_get_product( $product_id );
            if ( ! $product || ! $product->is_in_stock() ) {
                $errors[] = $product_id;
                continue;
            }

            // For booking items always qty = 1
            if ( $is_booking ) {
                $quantity = 1;
            }

            // Build cart item data (meta for cart/checkout display)
            $cart_item_data = array();
            if ( $is_booking && $booking_start ) {
                $cart_item_data['sdbpc_booking_start'] = $booking_start;
                $cart_item_data['sdbpc_booking_end']   = $booking_end ?: $booking_start;
                $cart_item_data['sdbpc_is_booking']    = true;

                // Also set rentman session values so rentman plugin picks them up
                if ( WC()->session ) {
                    WC()->session->set( 'rentman_rental_period_start', strtotime( $booking_start ) );
                    WC()->session->set( 'rentman_rental_period_end',   strtotime( $booking_end ?: $booking_start ) );
                }
            }

            $result = WC()->cart->add_to_cart( $product_id, $quantity, 0, array(), $cart_item_data );

            if ( $result ) {
                $added++;
            } else {
                $errors[] = $product_id;
            }
        }

        if ( $added === 0 ) {
            wp_send_json_error( array(
                'message' => 'Could not add any products to cart.',
                'errors'  => $errors,
            ) );
        }

        wp_send_json_success( array(
            'added'    => $added,
            'errors'   => $errors,
            'cart_url' => wc_get_cart_url(),
        ) );
    }

    /* ── Display booking meta in cart ─────────────────────────────────────── */
    public static function display_booking_meta_cart( $item_data, $cart_item ) {
        if ( ! empty( $cart_item['sdbpc_booking_start'] ) ) {
            $start = $cart_item['sdbpc_booking_start'];
            $end   = isset( $cart_item['sdbpc_booking_end'] ) ? $cart_item['sdbpc_booking_end'] : $start;

            $item_data[] = array(
                'key'   => __( 'Datum van de reveal', 'sdb-product-configurator' ),
                'value' => $start . ( $end && $end !== $start ? ' → ' . $end : '' ),
            );
        }
        return $item_data;
    }

    /* ── Save booking meta to order line item ─────────────────────────────── */
    public static function save_booking_meta_order( $item, $cart_item_key, $cart_item ) {
        if ( ! empty( $cart_item['sdbpc_booking_start'] ) ) {
            $start = $cart_item['sdbpc_booking_start'];
            $end   = isset( $cart_item['sdbpc_booking_end'] ) ? $cart_item['sdbpc_booking_end'] : $start;

            $item->add_meta_data( __( 'Datum van de reveal', 'sdb-product-configurator' ), $start . ( $end && $end !== $start ? ' → ' . $end : '' ) );
        }
    }
}
