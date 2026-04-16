<?php

namespace WPHZ\UGC\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * PriceHelper.
 */
class PriceHelper {

	/**
	 * Determines and returns the strict, raw price HTML of a WooCommerce product.
	 * This explicitly circumvents `$product->get_price_html()` to prevent
	 * 3rd-party plugins from appending promotional strings like "Subscribe & Save".
	 *
	 * @param \WC_Product $product
	 * @return string
	 */
	public static function get_clean_price( \WC_Product $product ): string {
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
