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
 * @package WPHZ\UGCCarousels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPHZ_UGCC_VERSION', '1.0.8' );
define( 'WPHZ_UGCC_FILE', __FILE__ );
define( 'WPHZ_UGCC_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPHZ_UGCC_URL', plugin_dir_url( __FILE__ ) );
define( 'WPHZ_UGCC_TEMPLATES', WPHZ_UGCC_DIR . 'templates/' );

require_once WPHZ_UGCC_DIR . 'vendor/autoload.php';

register_activation_hook( __FILE__, array( \WPHZ\UGCCarousels\Installer\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \WPHZ\UGCCarousels\Installer\Installer::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \WPHZ\UGCCarousels\Plugin::class, 'instance' ) );
