<?php
/**
 * Main plugin class.
 *
 * @package SDB_Product_Configurator
 */

defined( 'ABSPATH' ) || exit;

class SDB_Product_Configurator {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init',               array( $this, 'load_textdomain' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_footer',          array( $this, 'render_modal' ) );

        SDB_Configurator_Ajax::init();
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'sdb-product-configurator',
            false,
            dirname( plugin_basename( SDBPC_PLUGIN_FILE ) ) . '/languages/'
        );
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'sdbpc-style',
            SDBPC_PLUGIN_URL . 'assets/css/configurator.css',
            array(),
            rand()
        );

        wp_enqueue_script(
            'sdbpc-script',
            SDBPC_PLUGIN_URL . 'assets/js/configurator.js',
            array( 'jquery' ),
            rand(),
            true
        );

        $G = 'SDB_Configurator_Settings';

        // Build tiers map with full step config including booking fields
        $tiers_map = array();
        foreach ( $G::get_tiers() as $tier ) {
            $prefix     = strtoupper( $tier['prefix'] );
            $raw_steps  = isset( $tier['steps'] ) ? $tier['steps'] : array();
            $steps      = array();

            foreach ( $raw_steps as $step ) {
                $s = array(
                    'key'   => isset( $step['key'] )   ? $step['key']   : '',
                    'label' => isset( $step['label'] ) ? $step['label'] : '',
                    'type'  => isset( $step['type'] )  ? $step['type']  : 'product',
                );
                if ( $s['type'] === 'booking' ) {
                    $s['booking_product_id'] = isset( $step['booking_product_id'] ) ? (int) $step['booking_product_id'] : 0;
                    $s['booking_min_days']   = isset( $step['booking_min_days'] )   ? (int) $step['booking_min_days']   : 3;
                    $s['booking_show_end']   = isset( $step['booking_show_end'] )   ? ( $step['booking_show_end'] === '1' ) : false;
                }
                $steps[] = $s;
            }

            $tiers_map[ $prefix ] = array(
                'title' => isset( $tier['title'] ) ? $tier['title'] : '',
                'steps' => $steps,
            );
        }

        $datepicker_type = get_option( 'rentman_datepicker_type', 'date' );

        wp_localize_script( 'sdbpc-script', 'sdbpcConfig', array(
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'sdbpc_nonce' ),
            'cartUrl'        => wc_get_cart_url(),
            'currency'       => get_woocommerce_currency_symbol(),
            'datePickerType' => $datepicker_type,
            'tiers'          => $tiers_map,
            'i18n'           => array(
                'emptyCart'          => $G::get( 'cart_empty',      'Winkelwagen is leeg' ),
                'cartTitle'          => $G::get( 'cart_title',      'Winkelwagen' ),
                'totalLabel'         => $G::get( 'total_label',     'Totaal incl. BTW' ),
                'finish'             => $G::get( 'btn_finish',      'Voltooien' ),
                'nextStep'           => $G::get( 'btn_next',        'Naar stap %d >' ),
                'noProducts'         => $G::get( 'no_products_msg', 'Geen producten beschikbaar voor deze stap.' ),
                'addingToCart'       => __( 'Toevoegen aan winkelwagen\xe2\x80\xa6', 'sdb-product-configurator' ),
                'selectProducts'     => __( 'Voeg eerst producten toe aan uw winkelwagen', 'sdb-product-configurator' ),
                'errorAddingCart'    => __( 'Er is een fout opgetreden. Probeer het opnieuw.', 'sdb-product-configurator' ),
                'productId'          => __( 'Product ID:', 'sdb-product-configurator' ),
                'bookingStartDate'   => __( 'Datum van de reveal', 'sdb-product-configurator' ),
                'bookingEndDate'     => __( 'Einddatum', 'sdb-product-configurator' ),
                'bookingConfirmBtn'  => __( 'Datum bevestigen', 'sdb-product-configurator' ),
                'bookingChange'      => __( 'Datum wijzigen', 'sdb-product-configurator' ),
                'bookingConfirmed'   => __( 'Datum bevestigd:', 'sdb-product-configurator' ),
                'bookingSelectDate'  => __( 'Selecteer een datum.', 'sdb-product-configurator' ),
            ),
        ) );
    }

    public function render_modal() {
        if ( is_admin() ) {
            return;
        }
        include SDBPC_PLUGIN_DIR . 'templates/modal.php';
    }
}
