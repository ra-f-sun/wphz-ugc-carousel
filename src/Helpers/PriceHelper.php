<?php
/**
 * Price formatting helpers for WooCommerce products.
 *
 * @package WPHZ\UGCCarousels
 */

namespace WPHZ\UGCCarousels\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PriceHelper.
 */
class PriceHelper {

	/**
	 * Determines and returns the strict, raw price HTML of a WooCommerce product.
	 * This explicitly circumvents `$product->get_price_html()` to prevent
	 * 3rd-party plugins from appending promotional strings like "Subscribe & Save".
	 *
	 * When the Autoship Cloud plugin is active, the displayed price follows the
	 * Autoship configuration:
	 *  - Autoship enabled (Schedule Options on + checkout price set) => Autoship
	 *    checkout price.
	 *  - Not Autoship enabled (no checkout price) => regular WooCommerce price.
	 *  - "Enable Schedule Options" unchecked => regular WooCommerce price, even if
	 *    the product still processes through Autoship internally.
	 *
	 * @since  1.0.0
	 * @param  \WC_Product $product Product to format.
	 * @return string Price HTML string.
	 */
	public static function get_clean_price( \WC_Product $product ): string {
		$autoship_price = self::get_autoship_checkout_price( $product );
		if ( null !== $autoship_price ) {
			return wc_price( $autoship_price );
		}

		return self::get_woocommerce_price( $product );
	}

	/**
	 * Resolves the Autoship checkout price to display, or null when the regular
	 * WooCommerce price should be shown instead.
	 *
	 * @since  1.1.0
	 * @param  \WC_Product $product Product to inspect.
	 * @return float|null Autoship checkout price, or null to fall back to WC price.
	 */
	private static function get_autoship_checkout_price( \WC_Product $product ): ?float {
		// Autoship Cloud not active; nothing to override.
		if ( ! function_exists( 'autoship_schedule_options_enabled' )
			|| ! function_exists( 'autoship_get_product_checkout_price' ) ) {
			return null;
		}

		// "Enable Schedule Options" unchecked => excluded from Autoship on the
		// frontend, so always show the regular price.
		if ( 'yes' !== autoship_schedule_options_enabled( $product ) ) {
			return null;
		}

		// No Autoship checkout price configured => not Autoship enabled.
		$checkout_price = autoship_get_product_checkout_price( $product->get_id() );
		if ( '' === $checkout_price || null === $checkout_price ) {
			return null;
		}

		return (float) $checkout_price;
	}

	/**
	 * Returns the strict, raw WooCommerce price HTML of a product.
	 *
	 * @since  1.1.0
	 * @param  \WC_Product $product Product to format.
	 * @return string Price HTML string.
	 */
	private static function get_woocommerce_price( \WC_Product $product ): string {
		$price = '';

		if ( $product->is_type( 'variable' ) ) {
			$min = $product->get_variation_price( 'min', true );
			$max = $product->get_variation_price( 'max', true );
			if ( $min !== $max ) {
				$price = wc_format_price_range( $min, $max );
			} else {
				$price = wc_price( $min );
			}
		} elseif ( $product->is_on_sale() ) {
				$price = wc_format_sale_price( $product->get_regular_price(), $product->get_sale_price() );
		} else {
			$price = wc_price( $product->get_price() );
		}

		return $price;
	}
}
