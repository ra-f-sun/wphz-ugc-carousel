<?php
/**
 * Core plugin bootstrap.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGC\Admin\AdminMenu;
use WPHZ\UGC\Admin\AssetLoader as AdminAssets;
use WPHZ\UGC\Shortcode\ShortcodeRegistrar;
use WPHZ\UGC\Frontend\AssetLoader as FrontendAssets;
use WPHZ\UGC\Ajax\SaveConfig;
use WPHZ\UGC\Ajax\SaveContent;
use WPHZ\UGC\Ajax\ProductSearch;
use WPHZ\UGC\Ajax\DeleteItem;
use WPHZ\UGC\Ajax\CreateCarousel;
use WPHZ\UGC\Ajax\DeleteCarousel;
use WPHZ\UGC\Ajax\ExportCsv;
use WPHZ\UGC\Ajax\ImportCsv;
use WPHZ\UGC\Frontend\CartHandler;
use WPHZ\UGC\Ajax\SaveCustomCss;
use WPHZ\UGC\Ajax\DuplicateCarousel;
use WPHZ\UGC\Installer\Installer;

/**
 * Plugin.
 */
final class Plugin extends AbstractSingleton {


	/**
	 * Construct the plugin instance and wire all hooks.
	 *
	 * @since  1.0.0
	 */
	protected function __construct() {
		$this->init_hooks();
	}

	/**
	 * Register all WordPress hooks and initialize plugin components.
	 *
	 * Bails early with an admin notice if WooCommerce is not installed or inactive.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function init_hooks(): void {
		$woo_status = $this->get_woocommerce_status();

		if ( ! $woo_status['installed'] ) {
			add_action( 'admin_notices', array( $this, 'notice_wc_not_installed' ) );
			return;
		}

		if ( ! $woo_status['active'] ) {
			add_action( 'admin_notices', array( $this, 'notice_wc_inactive' ) );
			return;
		}

		add_action(
			'init',
			function () {
				if (
				get_option( 'wphz_ugc_db_version' ) !== WPHZ_UGC_VERSION
				|| ! Installer::items_has_poster_column()
				|| ! Installer::carousels_has_hide_atc_column()
				) {
					Installer::activate();
				}
			}
		);

		AdminMenu::instance()->init();
		AdminAssets::instance()->init();
		ShortcodeRegistrar::instance()->init();
		FrontendAssets::instance()->init();
		CartHandler::instance()->init();

		SaveConfig::instance()->init();
		SaveContent::instance()->init();
		SaveCustomCss::instance()->init();
		ProductSearch::instance()->init();
		DeleteItem::instance()->init();
		CreateCarousel::instance()->init();
		DeleteCarousel::instance()->init();
		DuplicateCarousel::instance()->init();
		ExportCsv::instance()->init();
		ImportCsv::instance()->init();
	}

	/**
	 * Determine whether WooCommerce is installed and active.
	 *
	 * @since  1.0.0
	 * @return array{installed: bool, active: bool}
	 */
	private function get_woocommerce_status(): array {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin    = 'woocommerce/woocommerce.php';
		$installed = file_exists( WP_PLUGIN_DIR . '/' . $plugin );

		$active = false;
		if ( $installed && function_exists( 'is_plugin_active' ) ) {
			$active = is_plugin_active( $plugin );

			if ( is_multisite() && function_exists( 'is_plugin_active_for_network' ) ) {
				$active = $active || is_plugin_active_for_network( $plugin );
			}
		}

		return array(
			'installed' => $installed,
			'active'    => $active,
		);
	}

	/**
	 * Output an admin notice when WooCommerce is not installed.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function notice_wc_not_installed(): void {
		echo '<div class="notice notice-error"><p>'
			. esc_html__( 'WPHZ UGC Carousel requires WooCommerce to be installed.', 'wphz-ugc' )
			. '</p></div>';
	}

	/**
	 * Output an admin notice when WooCommerce is installed but not active.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function notice_wc_inactive(): void {
		echo '<div class="notice notice-error"><p>'
			. esc_html__( 'WPHZ UGC Carousel requires WooCommerce to be active.', 'wphz-ugc' )
			. '</p></div>';
	}
}
