<?php

namespace WPHZ\UGC\Ajax;

defined('ABSPATH') || exit;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\NonceHelper;
use WPHZ\UGC\Helpers\TransientHelper;

class ProductSearch extends AbstractSingleton
{

    public function init(): void
    {
        add_action('wp_ajax_wphz_ugc_product_search', [$this, 'handle']);
    }

    public function handle(): void
    {
        NonceHelper::verify('wphz_ugc_admin');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.'], 403);
        }

        $term = sanitize_text_field($_GET['term'] ?? '');

        if (strlen($term) < 2) {
            wp_send_json_success([]);
        }

        // 'v2_' prefix busts caches from the old format (no status/sku fields)
        $key    = TransientHelper::product_key('v2_' . $term);
        $cached = TransientHelper::get($key);

        if ($cached !== null) {
            wp_send_json_success($cached);
        }

        global $wpdb;

        $ordered_ids = [];

        // 1. Exact SKU match (case-insensitive)
        $exact_sku_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = '_sku' AND LOWER(meta_value) = LOWER(%s)
                 LIMIT 5",
                $term
            )
        );
        foreach ($exact_sku_ids as $id) {
            $ordered_ids[] = (int) $id;
        }

        // 2. Partial SKU match
        $partial_sku_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = '_sku' AND LOWER(meta_value) LIKE LOWER(%s)
                 LIMIT 10",
                '%' . $wpdb->esc_like($term) . '%'
            )
        );
        foreach ($partial_sku_ids as $id) {
            $id = (int) $id;
            if (!in_array($id, $ordered_ids, true)) {
                $ordered_ids[] = $id;
            }
        }

        // 3. Name search — include all statuses so drafts/private products are found
        $name_query = new \WC_Product_Query([
            'limit'   => 10,
            's'       => $term,
            'orderby' => 'relevance',
            'return'  => 'ids',
            'status'  => ['publish', 'draft', 'private', 'pending'],
        ]);
        foreach ($name_query->get_products() as $id) {
            $id = (int) $id;
            if (!in_array($id, $ordered_ids, true)) {
                $ordered_ids[] = $id;
            }
        }

        // 4. Numeric ID lookup
        if (is_numeric($term)) {
            $numeric_id = (int) $term;
            if (!in_array($numeric_id, $ordered_ids, true)) {
                $ordered_ids[] = $numeric_id;
            }
        }

        // Build result set — no publish-only filter; show all valid products with status info
        $results = [];
        foreach (array_slice($ordered_ids, 0, 15) as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) continue;

            $results[] = [
                'id'                 => $product->get_id(),
                'name'               => $product->get_name(),
                'sku'                => $product->get_sku(),
                'price_html'         => wp_strip_all_tags(\WPHZ\UGC\Helpers\PriceHelper::get_clean_price($product)),
                'thumbnail'          => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                'status'             => $product->get_status(),
                'catalog_visibility' => $product->get_catalog_visibility(),
            ];
        }

        TransientHelper::set($key, $results);
        wp_send_json_success($results);
    }
}
