<?php
namespace WPHZ\UGC\Admin;

use WPHZ\UGC\AbstractSingleton;

class AssetLoader extends AbstractSingleton {

    public function init(): void {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(string $hook): void {
        // Only load on this plugin's admin page
        if (!str_contains($hook, 'wphz-ugc-carousel')) {
            return;
        }

        wp_enqueue_style(
            'wphz-ugc-admin',
            WPHZ_UGC_URL . 'assets/admin/admin.css',
            [],
            WPHZ_UGC_VERSION
        );

        // Required for the WP media picker used in the Content tab (Phase 4)
        wp_enqueue_media();

        wp_enqueue_script(
            'wphz-ugc-admin',
            WPHZ_UGC_URL . 'assets/admin/admin.js',
            ['jquery', 'jquery-ui-sortable'],
            WPHZ_UGC_VERSION,
            true // footer
        );

        wp_localize_script('wphz-ugc-admin', 'wphzUGC', [
            'ajaxurl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('wphz_ugc_admin'),
            'mediaTitle'  => __('Select Video', 'wphz-ugc'),
            'mediaButton' => __('Use this video', 'wphz-ugc'),
        ]);
    }
}
