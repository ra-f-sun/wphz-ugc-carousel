<?php
defined('ABSPATH') || exit;
namespace WPHZ\UGC\Shortcode;

use WPHZ\UGC\AbstractSingleton;

class ShortcodeRegistrar extends AbstractSingleton {

    public function init(): void {
        add_shortcode('wphz_ugc_carousel', [ShortcodeRenderer::instance(), 'render']);
    }
}
