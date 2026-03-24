<?php
namespace WPHZ\UGC\Ajax;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\NonceHelper;
use WPHZ\UGC\Helpers\TransientHelper;

class ProductSearch extends AbstractSingleton {

    public function init(): void {
        add_action('wp_ajax_wphz_ugc_product_search', [$this, 'handle']);
    }

    public function handle(): void {
        NonceHelper::verify('wphz_ugc_admin');

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

        // Query WooCommerce products
        $query = new \WC_Product_Query([
            'limit'   => 15,
            'status'  => 'publish',
            's'       => $term,
            'orderby' => 'relevance',
            'return'  => 'objects',
        ]);

        $products = $query->get_products();
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
