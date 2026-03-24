<?php
namespace WPHZ\UGC\Helpers;

class NonceHelper {

    /**
     * Verify a WordPress nonce. Checks both $_REQUEST['nonce'] and $_REQUEST['wphz_nonce'].
     * Sends 403 JSON error and exits on failure.
     */
    public static function verify(string $action): void {
        $nonce = sanitize_text_field(
            $_REQUEST['nonce'] ?? $_REQUEST['wphz_nonce'] ?? ''
        );
        if (!wp_verify_nonce($nonce, $action)) {
            wp_send_json_error(['message' => 'Security check failed.'], 403);
        }
    }
}
