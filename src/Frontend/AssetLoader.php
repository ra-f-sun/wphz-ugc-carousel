<?php
/**
 * Frontend asset loading for the plugin.
 *
 * @package WPHZ\UGCCarousels
 */

namespace WPHZ\UGCCarousels\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGCCarousels\AbstractSingleton;

/**
 * AssetLoader.
 */
class AssetLoader extends AbstractSingleton {

	/**
	 * Guards against double-enqueue when shortcode appears multiple times on a page.
	 *
	 * @var bool
	 */
	private bool $enqueued = false;

	/**
	 * Register WordPress hooks for this component.
	 *
	 * Intentionally a no-op — assets are lazy-loaded via enqueue_now() only when
	 * the shortcode is actually rendered on a given request.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function init(): void {
		// Intentionally empty: enqueue happens lazily via enqueue_now().
	}

	/**
	 * Enqueue frontend CSS and JS on first shortcode render; subsequent calls are no-ops.
	 *
	 * @since  1.0.0
	 * @param  array<string, mixed> $config Resolved carousel configuration data.
	 * @return void
	 */
	public function enqueue_now( array $config ): void {
		if ( $this->enqueued ) {
			return;
		}
		$this->enqueued = true;

		wp_enqueue_style(
			'ugcc-frontend',
			WPHZ_UGCC_URL . 'assets/frontend/carousel.css',
			array( 'woocommerce-general' ), // depend on WC stylesheet for price formatting.
			WPHZ_UGCC_VERSION
		);

		wp_enqueue_script(
			'ugcc-poster-engine',
			WPHZ_UGCC_URL . 'assets/frontend/poster-engine.js',
			array(),
			WPHZ_UGCC_VERSION,
			true
		);

		wp_enqueue_script(
			'ugcc-frontend',
			WPHZ_UGCC_URL . 'assets/frontend/carousel.js',
			array( 'ugcc-poster-engine' ), // no jQuery dependency - vanilla JS.
			WPHZ_UGCC_VERSION,
			true           // load in footer.
		);

		// C4 contract: exact key names required - JS reads window.ugccFrontend.*.
		wp_localize_script(
			'ugcc-frontend',
			'ugccFrontend',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ugcc_atc' ),
				'danger'  => array(
					'on_arrow_right' => $config['on_arrow_right'] ?? '',
					'on_arrow_left'  => $config['on_arrow_left'] ?? '',
				),
				'i18n'    => array(
					'added' => __( 'Added!', 'ugc-carousels-for-woo' ),
					'error' => __( 'Error - retry.', 'ugc-carousels-for-woo' ),
				),
			)
		);
	}
}
