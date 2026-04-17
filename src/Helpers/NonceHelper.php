<?php
/**
 * Nonce verification helpers for the plugin.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonceHelper.
 */
class NonceHelper {

	/**
	 * Verify a WordPress nonce from the current request.
	 *
	 * Checks POST then GET for both `nonce` and `wphz_nonce` field names.
	 * Calls wp_send_json_error() with HTTP 403 and exits if verification fails.
	 *
	 * @since  1.0.0
	 * @param  string $action The nonce action string to verify against.
	 * @return void
	 */
	public static function verify( string $action ): void {
		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS )
			?? filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS )
			?? filter_input( INPUT_POST, 'wphz_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS )
			?? filter_input( INPUT_GET, 'wphz_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS )
			?? '';
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
		}
	}
}
