<?php
/**
 * Plugin Name: SDB Product Configurator
 * Description: Step-by-step product configurator popup (Tiers & Steps) plus a category-filtered "Product Range" shortcode for WooCommerce.
 * Version:     1.1.0
 * Author:      SDB
 * Text Domain: sdb-product-configurator
 * Domain Path: /languages
 *
 * @package SDB_Product_Configurator
 */

defined( 'ABSPATH' ) || exit;

define( 'SDBPC_PLUGIN_FILE', __FILE__ );
define( 'SDBPC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SDBPC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SDBPC_VERSION', '1.1.0' );

require_once SDBPC_PLUGIN_DIR . 'includes/class-sdb-configurator-settings.php';
require_once SDBPC_PLUGIN_DIR . 'includes/class-sdb-configurator-ajax.php';
require_once SDBPC_PLUGIN_DIR . 'includes/class-sdb-product-configurator.php';
require_once SDBPC_PLUGIN_DIR . 'includes/class-sdb-range-settings.php';
require_once SDBPC_PLUGIN_DIR . 'includes/class-sdb-range-ajax.php';
require_once SDBPC_PLUGIN_DIR . 'includes/class-sdb-product-range.php';

add_action( 'plugins_loaded', 'sdbpc_bootstrap' );

/**
 * Boot the plugin once all plugins (incl. WooCommerce) are loaded.
 */
function sdbpc_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'sdbpc_missing_woocommerce_notice' );
		return;
	}

	SDB_Configurator_Settings::init();
	SDB_Product_Configurator::instance();
	SDB_Range_Ajax::init();
	SDB_Product_Range::init();
}

/**
 * Admin notice shown when WooCommerce is not active.
 */
function sdbpc_missing_woocommerce_notice() {
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'SDB Product Configurator requires WooCommerce to be installed and active.', 'sdb-product-configurator' ) .
		'</p></div>';
}
