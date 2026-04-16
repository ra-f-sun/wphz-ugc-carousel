<?php
/**
 * Template loading helpers for the plugin.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Template loading helpers.
 */
class TemplateLoader {

	/**
	 * Render a template by name using local variables from the provided data.
	 *
	 * @param string       $template Template path relative to the templates/ dir, without .php extension.
	 * @param array<mixed> $data Variables to expose to the template scope.
	 */
	public static function render( string $template, array $data = array() ): void {
		$file = WPHZ_UGC_TEMPLATES . ltrim( $template, '/' ) . '.php';
		if ( ! file_exists( $file ) ) {
			return;
		}

		foreach ( $data as $key => $value ) {
			if ( ! is_string( $key ) || '' === $key ) {
				continue;
			}

			if ( 1 !== preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $key ) ) {
				continue;
			}

			if ( isset( ${$key} ) ) {
				continue;
			}

			${$key} = $value;
		}

		include $file;
	}

	/**
	 * Same as render() but captures and returns output as a string.
	 *
	 * @param string       $template Template path relative to the templates/ dir, without .php extension.
	 * @param array<mixed> $data Variables to expose to the template scope.
	 * @return string Rendered template output.
	 */
	public static function render_return( string $template, array $data = array() ): string {
		ob_start();
		self::render( $template, $data );
		return (string) ob_get_clean();
	}
}
