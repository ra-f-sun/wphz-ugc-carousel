<?php
defined('ABSPATH') || exit;
namespace WPHZ\UGC\Helpers;

class TransientHelper {

    private const PREFIX      = 'wphz_ugc_';
    private const PRODUCT_TTL = 300; // 5 minutes

    public static function product_key(string $search_term): string {
        return self::PREFIX . 'product_' . md5(strtolower(trim($search_term)));
    }

    public static function get(string $key): mixed {
        $value = get_transient($key);
        return ($value === false) ? null : $value;
    }

    public static function set(string $key, mixed $value, int $expiry = self::PRODUCT_TTL): void {
        set_transient($key, $value, $expiry);
    }

    public static function flush_products(): void {
        // WooCommerce helper to clear product transients
        wc_delete_product_transients();
        // Clear our own search-result transients by SQL LIKE pattern
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . self::PREFIX . 'product_%'
            )
        );
    }
}
