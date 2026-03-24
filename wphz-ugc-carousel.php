<?php
/**
 * Plugin Name: WPHZ UGC Carousel
 * Plugin URI:  https://wphelpzone.com
 * Description: User-Generated Content video carousel with WooCommerce product attachment.
 * Version:     1.0.2
 * Author:      WPHelpZone LLC
 * Text Domain: wphz-ugc
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 */

defined('ABSPATH') || exit;

define('WPHZ_UGC_VERSION',   '1.0.2');
define('WPHZ_UGC_FILE',      __FILE__);
define('WPHZ_UGC_DIR',       plugin_dir_path(__FILE__));
define('WPHZ_UGC_URL',       plugin_dir_url(__FILE__));
define('WPHZ_UGC_TEMPLATES', WPHZ_UGC_DIR . 'templates/');

require_once WPHZ_UGC_DIR . 'vendor/autoload.php';

register_activation_hook(__FILE__, [\WPHZ\UGC\Installer\Installer::class, 'activate']);
register_deactivation_hook(__FILE__, [\WPHZ\UGC\Installer\Installer::class, 'deactivate']);

add_action('plugins_loaded', [\WPHZ\UGC\Plugin::class, 'instance']);
