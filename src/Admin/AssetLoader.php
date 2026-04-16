<?php
/**
 * Admin asset loading.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGC\AbstractSingleton;

/**
 * AssetLoader.
 */
class AssetLoader extends AbstractSingleton {

	/**
	 * Initialize hooks.
	 *
	 * @return void Return value.
	 */
	public function init(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Parameter value.
	 * @return void Return value.
	 */
	public function enqueue( string $hook ): void {
		if ( ! str_contains( $hook, 'wphz-ugc-carousel' ) ) {
			return;
		}

		wp_enqueue_style(
			'wphz-ugc-admin',
			WPHZ_UGC_URL . 'assets/admin/admin.css',
			array(),
			WPHZ_UGC_VERSION
		);

		// Required for the WP media picker used in the Content tab.
		wp_enqueue_media();

		wp_enqueue_script(
			'wphz-ugc-admin',
			WPHZ_UGC_URL . 'assets/admin/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			WPHZ_UGC_VERSION,
			true // footer.
		);

		wp_localize_script(
			'wphz-ugc-admin',
			'wphzUGC',
			array(
				'ajaxurl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'wphz_ugc_admin' ),
				'mediaTitle'  => __( 'Select Video', 'wphz-ugc' ),
				'mediaButton' => __( 'Use this video', 'wphz-ugc' ),
			)
		);

		// CodeMirror for the Custom CSS tab only.
		// filter_input() is the correct, injection-safe way to read $_GET in WP.
		// $GLOBALS['_GET'] and direct $_GET access both work but bypass filtering.
		$action = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS ) ?? '';
		$tab    = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS ) ?? '';

		if ( 'edit' === $action && 'custom-css' === $tab ) {
			// Returns false if the user disabled syntax highlighting â€” safe to ignore.
			wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
		}
	}
}
