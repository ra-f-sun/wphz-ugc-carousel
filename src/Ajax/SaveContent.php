<?php

namespace WPHZ\UGC\Ajax;
defined('ABSPATH') || exit;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\NonceHelper;
use WPHZ\UGC\Installer\Installer;
use WPHZ\UGC\Repository\ItemRepository;

class SaveContent extends AbstractSingleton
{

    public function init(): void
    {
        add_action('wp_ajax_wphz_ugc_save_content', [$this, 'handle']);
    }

    public function handle(): void
    {
        NonceHelper::verify('wphz_ugc_admin');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.'], 403);
        }

        $items_raw   = $_POST['items'] ?? [];
        $carousel_id = (int) ($_POST['carousel_id'] ?? 0);

        if ($carousel_id <= 0) {
            wp_send_json_error(['message' => __('Invalid Carousel ID.', 'wphz-ugc')]);
        }

        if (!Installer::items_has_poster_column()) {
            wp_send_json_error([
                'message' => __('Database schema is outdated. Please reload the page and try again.', 'wphz-ugc')
            ]);
        }

        $repo = ItemRepository::instance();

        // Delete-all then re-insert: simple replace-all strategy scoped to parent ID.
        $this->clear_existing($carousel_id);

        $sort = 0;
        foreach ($items_raw as $data) {
            $video_id     = (int) ($data['video_id']   ?? 0);
            $video_url_hd = esc_url_raw($data['video_url_hd'] ?? '');
            $video_url_sd = esc_url_raw($data['video_url_sd'] ?? '');
            $poster_url   = esc_url_raw($data['poster_url'] ?? '');

            $products_raw = (array) ($data['products'] ?? []);
            $product_ids  = [];
            foreach ($products_raw as $pdata) {
                if (empty($pdata['id'])) continue;
                // 3-state: null = inherit global, 0 = force show, 1 = force hide.
                // Empty string means "inherit" (sent by the select "Use global default" option).
                $hide_raw = $pdata['hide_atc'] ?? '';
                $product_ids[] = [
                    'id'       => (int) $pdata['id'],
                    'hide_atc' => ($hide_raw !== '' && $hide_raw !== null) ? (int) $hide_raw : null,
                ];
            }

            // Skip row if both URLs are missing. 
            // If one is missing, it will be skipped by the frontend dynamic engine fallback.
            if (!$video_url_hd && !$video_url_sd) {
                continue;
            }

            if (!$repo->insert([
                'carousel_id'  => (string) $carousel_id,
                'sort_order'   => $sort++,
                'video_id'     => $video_id,
                'video_url_hd' => $video_url_hd,
                'video_url_sd' => $video_url_sd,
                'poster_url'   => $poster_url,
                'product_ids'  => $product_ids,
            ])) {
                global $wpdb;
                wp_send_json_error([
                    'message' => __('Failed to save carousel content. Please retry after refreshing the page.', 'wphz-ugc'),
                    'debug'   => $wpdb->last_error,
                ]);
            }
        }

        wp_send_json_success([
            'message'   => __('Content saved.', 'wphz-ugc'),
            'shortcode' => sprintf('[wphz_ugc_carousel id="%d"]', $carousel_id)
        ]);
    }

    private function clear_existing(int $carousel_id): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wphz_ugc_items';
        $wpdb->delete($table, ['carousel_id' => (string) $carousel_id]);
    }
}
