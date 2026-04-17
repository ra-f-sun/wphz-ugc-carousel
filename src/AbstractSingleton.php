<?php
/**
 * Abstract singleton base class.
 *
 * @package WPHZ\UGCCarousels
 */

namespace WPHZ\UGCCarousels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides shared singleton instance handling.
 */
abstract class AbstractSingleton {
	/**
	 * Stores initialized singleton instances keyed by class name.
	 *
	 * @var array<string, static>
	 */
	private static array $instances = array();

	/**
	 * Prevent direct construction.
	 */
	protected function __construct() {}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Return the singleton instance for the called class, creating it if needed.
	 *
	 * @since  1.0.0
	 * @return static
	 */
	public static function instance(): static {
		$class = static::class;
		if ( ! isset( self::$instances[ $class ] ) ) {
			self::$instances[ $class ] = new static();
		}
		return self::$instances[ $class ];
	}
}
