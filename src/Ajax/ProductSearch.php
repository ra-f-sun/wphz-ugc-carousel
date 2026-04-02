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

        // Require at least 2 characters to search
        if (strlen($term) < 2) {
            wp_send_json_success([]);
        }

        // Check transient cache first (5-minute TTL)
        $key    = TransientHelper::product_key($term);
        $cached = TransientHelper::get($key);

        if ($cached !== null) {
            wp_send_json_success($cached);
        }

        // Check if term is numeric (could be product ID)
        $is_numeric = is_numeric($term);

        // Query WooCommerce products by name and optionally ID
        $query = new \WC_Product_Query([
            'limit'   => 15,
            'status'  => 'publish',
            's'       => $term,
            'orderby' => 'relevance',
            'return'  => 'objects',
        ]);

        $products = $query->get_products();
        $product_ids = wp_list_pluck($products, 'id');

        // Search by SKU using direct SQL query
        global $wpdb;
        $sku_query = $wpdb->prepare(
            "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_sku' AND meta_value LIKE %s
             LIMIT 15",
            '%' . $wpdb->esc_like($term) . '%'
        );
        $sku_product_ids = $wpdb->get_col($sku_query);

        // If numeric, also search by product ID
        if ($is_numeric) {
            $product_id = (int) $term;
            if (!in_array($product_id, $product_ids)) {
                $sku_product_ids[] = $product_id;
            }
        }

        // Merge all found product IDs and deduplicate
        $all_ids = array_unique(array_merge($product_ids, (array) $sku_product_ids));

        // Fetch the final products
        $products = [];
        foreach (array_slice($all_ids, 0, 15) as $product_id) {
            $product = wc_get_product($product_id);
            if ($product && $product->get_status() === 'publish') {
                $products[] = $product;
            }
        }
        $results  = array_map(fn($p) => [
            'id'         => $p->get_id(),
            'name'       => $p->get_name(),
            'price_html' => wp_strip_all_tags(\WPHZ\UGC\Helpers\PriceHelper::get_clean_price($p)),
            'thumbnail'  => wp_get_attachment_image_url($p->get_image_id(), 'thumbnail'),
        ], $products);

        TransientHelper::set($key, $results);
        wp_send_json_success($results);
    }
}
