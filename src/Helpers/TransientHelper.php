<?php
/**
 * Transient caching helpers for the plugin.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TransientHelper.
 */
class TransientHelper {

	private const PREFIX      = 'wphz_ugc_';
	private const PRODUCT_TTL = 300; // 5 minutes.

	/**
	 * Build a transient key for product searches.
	 *
	 * @param string $search_term Search term.
	 * @return string Transient key.
	 */
	public static function product_key( string $search_term ): string {
		return self::PREFIX . 'product_' . md5( strtolower( trim( $search_term ) ) );
	}

	/**
	 * Get a transient value.
	 *
	 * @param string $key Transient key.
	 * @return mixed Transient value or null when missing.
	 */
	public static function get( string $key ): mixed {
		$value = get_transient( $key );
		return ( false === $value ) ? null : $value;
	}

	/**
	 * Set a transient value.
	 *
	 * @param string $key Transient key.
	 * @param mixed  $value Transient value.
	 * @param int    $expiry Expiration in seconds.
	 * @return void
	 */
	public static function set( string $key, mixed $value, int $expiry = self::PRODUCT_TTL ): void {
		set_transient( $key, $value, $expiry );
	}

	/**
	 * Flush product-related transients.
	 *
	 * @return void
	 */
	public static function flush_products(): void {
		// WooCommerce helper to clear product transients.
		wc_delete_product_transients();
		// Clear our own search-result transients by SQL LIKE pattern.
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_' . self::PREFIX . 'product_%'
			)
		);
	}
}
