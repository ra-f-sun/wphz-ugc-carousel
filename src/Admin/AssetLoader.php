<?php
/**
 * Admin asset loading.
 *
 * @package WPHZ\UGCCarousels
 */

namespace WPHZ\UGCCarousels\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGCCarousels\AbstractSingleton;

/**
 * AssetLoader.
 */
class AssetLoader extends AbstractSingleton {

	/**
	 * Register WordPress hooks for this component.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue admin CSS, JS, and localized data — only on plugin admin pages.
	 *
	 * @since  1.0.0
	 * @param  string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( ! str_contains( $hook, 'ugc-carousels-for-woo' ) ) {
			return;
		}

		wp_enqueue_style(
			'ugcc-admin',
			WPHZ_UGCC_URL . 'assets/admin/admin.css',
			array(),
			WPHZ_UGCC_VERSION
		);

		// Required for the WP media picker used in the Content tab.
		wp_enqueue_media();

		wp_enqueue_script(
			'ugcc-admin',
			WPHZ_UGCC_URL . 'assets/admin/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			WPHZ_UGCC_VERSION,
			true // footer.
		);

		wp_localize_script(
			'ugcc-admin',
			'ugccAdmin',
			array(
				'ajaxurl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'ugcc_admin' ),
				'mediaTitle'  => __( 'Select Video', 'ugc-carousels-for-woo' ),
				'mediaButton' => __( 'Use this video', 'ugc-carousels-for-woo' ),
			)
		);

		// CodeMirror for the Custom CSS tab only.
		// filter_input() is the correct, injection-safe way to read $_GET in WP.
		// $GLOBALS['_GET'] and direct $_GET access both work but bypass filtering.
		$action = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS ) ?? '';
		$tab    = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS ) ?? '';

		if ( 'edit' === $action && 'custom-css' === $tab ) {
			// Returns false if the user disabled syntax highlighting — safe to ignore.
			wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
		}
	}
}
