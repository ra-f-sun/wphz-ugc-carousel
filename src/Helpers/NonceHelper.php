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
	 * Verify a WordPress nonce. Checks both $_REQUEST['nonce'] and $_REQUEST['wphz_nonce'].
	 * Sends 403 JSON error and exits on failure.
	 *
	 * @param string $action Nonce action name.
	 */
	public static function verify( string $action ): void {
		$nonce = sanitize_text_field(
			$_REQUEST['nonce'] ?? $_REQUEST['wphz_nonce'] ?? ''
		);
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
		}
	}
}
