<?php
/**
 * Shortcode registration for the plugin.
 *
 * @package WPHZ\UGCCarousels
 */

namespace WPHZ\UGCCarousels\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGCCarousels\AbstractSingleton;

/**
 * ShortcodeRegistrar.
 */
class ShortcodeRegistrar extends AbstractSingleton {


	/**
	 * Register WordPress hooks for this component.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function init(): void {
		add_shortcode( 'wphz_ugc_carousel', array( ShortcodeRenderer::instance(), 'render' ) );
	}
}
