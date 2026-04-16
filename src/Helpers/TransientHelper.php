<?php

namespace WPHZ\UGC\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * TransientHelper.
 */
class TransientHelper {

	private const PREFIX      = 'wphz_ugc_';
	private const PRODUCT_TTL = 300; // 5 minutes.

	/**
	 * product_key.
	 *
	 * @param mixed $search_term Parameter value.
	 * @return string Return value.
	 */
	public static function product_key( string $search_term ): string {
		return self::PREFIX . 'product_' . md5( strtolower( trim( $search_term ) ) );
	}

	/**
	 * get.
	 *
	 * @param mixed $key Parameter value.
	 * @return mixed Return value.
	 */
	public static function get( string $key ): mixed {
		$value = get_transient( $key );
		return ( $value === false ) ? null : $value;
	}

	/**
	 * set.
	 *
	 * @param mixed $key Parameter value.
	 * @param mixed $value Parameter value.
	 * @param mixed $expiry Parameter value.
	 * @return void Return value.
	 */
	public static function set( string $key, mixed $value, int $expiry = self::PRODUCT_TTL ): void {
		set_transient( $key, $value, $expiry );
	}

	/**
	 * flush_products.
	 *
	 * @return void Return value.
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
