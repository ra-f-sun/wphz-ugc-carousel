<?php

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
	 * init.
	 *
	 * @return void Return value.
	 */
	public function init(): void {
		add_shortcode( 'wphz_ugc_carousel', array( ShortcodeRenderer::instance(), 'render' ) );
	}
}
