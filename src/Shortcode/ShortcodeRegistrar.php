<?php
/**
 * Shortcode registration for the plugin.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGC\AbstractSingleton;

/**
 * ShortcodeRegistrar.
 */
class ShortcodeRegistrar extends AbstractSingleton {


	/**
	 * Initialize shortcode hooks.
	 *
	 * @return void Return value.
	 */
	public function init(): void {
		add_shortcode( 'wphz_ugc_carousel', array( ShortcodeRenderer::instance(), 'render' ) );
	}
}
