<?php
namespace WPHZ\UGC\Helpers;
defined('ABSPATH') || exit;

class SanitizeHelper {

    /**
     * Sanitize a plain text string (strips tags, extra whitespace).
     */
    public static function text(string $value): string {
        return sanitize_text_field($value);
    }

    /**
     * Sanitize a JS identifier or custom event name.
     * Allows only alphanumeric, underscore, and dash — prevents XSS
     * in Danger Zone fields that get injected into wp_localize_script output.
     */
    public static function js_identifier(string $value): string {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $value);
    }

    /**
     * Sanitize a URL.
     */
    public static function url(string $value): string {
        return esc_url_raw($value);
    }

    /**
     * Cast all values of an array to integers.
     *
     * @param  array<mixed> $values
     * @return array<int>
     */
    public static function int_array(array $values): array {
        return array_map('intval', $values);
    }
}
