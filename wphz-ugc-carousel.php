<?php
/**
 * Plugin Name: UGC Carousels & Shoppable Videos for WooCommerce
 * Plugin URI:  https://www.wphelpzone.com/plugins/ugc-carousels-for-woo/
 * Description: User-Generated Content video carousel with WooCommerce product attachment.
 * Version:     1.0.8
 * Author:      WP Help Zone
 * Text Domain: ugc-carousels-for-woo
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 *
 * @package WPHZ\UGC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPHZ_UGC_VERSION', '1.0.8' );
define( 'WPHZ_UGC_FILE', __FILE__ );
define( 'WPHZ_UGC_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPHZ_UGC_URL', plugin_dir_url( __FILE__ ) );
define( 'WPHZ_UGC_TEMPLATES', WPHZ_UGC_DIR . 'templates/' );

require_once WPHZ_UGC_DIR . 'vendor/autoload.php';

register_activation_hook( __FILE__, array( \WPHZ\UGC\Installer\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \WPHZ\UGC\Installer\Installer::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \WPHZ\UGC\Plugin::class, 'instance' ) );
