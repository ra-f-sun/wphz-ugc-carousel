<?php
/**
 * Sanitization helpers used throughout the plugin.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SanitizeHelper.
 */
class SanitizeHelper {

	/**
	 * Sanitize a plain text string (strips tags, extra whitespace).
	 *
	 * @since  1.0.0
	 * @param  string $value Raw text value.
	 * @return string Sanitized text.
	 */
	public static function text( string $value ): string {
		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize a JS identifier or custom event name.
	 * Allows only alphanumeric, underscore, and dash - prevents XSS
	 * in Danger Zone fields that get injected into wp_localize_script output.
	 *
	 * @since  1.0.0
	 * @param  string $value Raw identifier value.
	 * @return string Sanitized identifier.
	 */
	public static function js_identifier( string $value ): string {
		return preg_replace( '/[^a-zA-Z0-9_\-]/', '', $value );
	}

	/**
	 * Sanitize a URL.
	 *
	 * @since  1.0.0
	 * @param  string $value Raw URL value.
	 * @return string Sanitized URL.
	 */
	public static function url( string $value ): string {
		return esc_url_raw( $value );
	}

	/**
	 * Cast all values of an array to integers.
	 *
	 * @since  1.0.0
	 * @param  array<mixed> $values Raw values.
	 * @return array<int>
	 */
	public static function int_array( array $values ): array {
		return array_map( 'intval', $values );
	}
}
