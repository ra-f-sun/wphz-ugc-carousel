<?php
defined('ABSPATH') || exit;

namespace WPHZ\UGC;

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
use WPHZ\UGC\Frontend\CartHandler;

final class Plugin extends AbstractSingleton
{

    protected function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks(): void
    {
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', [$this, 'notice_wc_missing']);
            return;
        }

        add_action('init', function () {
            if (
                get_option('wphz_ugc_db_version') !== WPHZ_UGC_VERSION
                || !\WPHZ\UGC\Installer\Installer::items_has_poster_column()
            ) {
                \WPHZ\UGC\Installer\Installer::activate();
            }
        });

        AdminMenu::instance()->init();
        AdminAssets::instance()->init();
        ShortcodeRegistrar::instance()->init();
        FrontendAssets::instance()->init();
        CartHandler::instance()->init();

        SaveConfig::instance()->init();
        SaveContent::instance()->init();
        \WPHZ\UGC\Ajax\SaveCustomCss::instance()->init();
        ProductSearch::instance()->init();
        DeleteItem::instance()->init();
        CreateCarousel::instance()->init();
        DeleteCarousel::instance()->init();
        \WPHZ\UGC\Ajax\DuplicateCarousel::instance()->init();
    }

    private function is_woocommerce_active(): bool
    {
        return class_exists('WooCommerce');
    }

    public function notice_wc_missing(): void
    {
        echo '<div class="notice notice-error"><p>'
            . esc_html__('WPHZ UGC Carousel requires WooCommerce to be active.', 'wphz-ugc')
            . '</p></div>';
    }
}
