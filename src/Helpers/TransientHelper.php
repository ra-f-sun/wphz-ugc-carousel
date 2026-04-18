<?php
/**
 * Transient caching helpers for the plugin.
 *
 * @package WPHZ\UGCCarousels
 */

namespace WPHZ\UGCCarousels\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TransientHelper.
 */
class TransientHelper {

	private const PREFIX      = 'ugcc_';
	private const PRODUCT_TTL = 300; // 5 minutes.

	/**
	 * Build a transient key for product searches.
	 *
	 * @since  1.0.0
	 * @param  string $search_term Search term.
	 * @return string Transient key.
	 */
	public static function product_key( string $search_term ): string {
		return self::PREFIX . 'product_' . md5( strtolower( trim( $search_term ) ) );
	}

	/**
	 * Get a transient value.
	 *
	 * @since  1.0.0
	 * @param  string $key Transient key.
	 * @return mixed  Cached value, or null if not found or expired.
	 */
	public static function get( string $key ): mixed {
		$value = get_transient( $key );
		return ( false === $value ) ? null : $value;
	}

	/**
	 * Store a value in the transient cache.
	 *
	 * @since  1.0.0
	 * @param  string $key    Transient key.
	 * @param  mixed  $value  Value to cache.
	 * @param  int    $expiry Time in seconds before expiry. Default: 300 (5 min).
	 * @return void
	 */
	public static function set( string $key, mixed $value, int $expiry = self::PRODUCT_TTL ): void {
		set_transient( $key, $value, $expiry );
	}

	/**
	 * Flush product-related transients.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function flush_products(): void {
		// WooCommerce helper to clear product transients.
		wc_delete_product_transients();
		// Clear our own search-result transients by SQL LIKE pattern.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WordPress provides no API to bulk-delete transients by LIKE pattern; get_transient()/delete_transient() operate on one key at a time. Direct SQL is the only way to atomically flush all plugin search caches. NoCaching does not apply to a cache-invalidation operation. The query is fully prepared and only targets option_name rows matching the plugin's own prefix.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_' . self::PREFIX . 'product_%'
			)
		);
	}
}
