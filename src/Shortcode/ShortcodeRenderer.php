<?php

namespace WPHZ\UGC\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Repository\CarouselRepository;
use WPHZ\UGC\Repository\ItemRepository;
use WPHZ\UGC\Helpers\TemplateLoader;
use WPHZ\UGC\Frontend\AssetLoader;

/**
 * ShortcodeRenderer.
 */
class ShortcodeRenderer extends AbstractSingleton {


	/**
	 * Main shortcode callback. Returns rendered HTML string (never echoes).
	 *
	 * @param  array<string, string>|string $atts  Shortcode attributes.
	 * @return string
	 */
	public function render( array|string $atts ): string {
		$atts        = shortcode_atts( array( 'id' => 0 ), (array) $atts, 'wphz_ugc_carousel' );
		$carousel_id = (int) $atts['id'];

		if ( $carousel_id <= 0 ) {
			return '<p>WPHZ UGC Carousel: Please provide a valid carousel ID in the shortcode.</p>';
		}

		$carousel_config = CarouselRepository::instance()->get_by_id( $carousel_id );
		if ( ! $carousel_config ) {
			return sprintf( '<p>WPHZ UGC Carousel: Carousel #%d not found.</p>', $carousel_id );
		}

		$items = ItemRepository::instance()->get_all( (string) $carousel_id );
		$items = $this->hydrate_items( $items );

		if ( empty( $items ) ) {
			return '';
		}

		// Lazy-enqueue frontend assets â€” AssetLoader receives the unified config array.
		AssetLoader::instance()->enqueue_now( $carousel_config );

		// Prepend scoped custom CSS if the carousel has any.
		// wp_add_inline_style() cannot be used here because shortcodes render.
		// during the_content(), which is after wp_head() has already fired.
		// A <style> tag inline in the output is the correct approach.
		$custom_css_output = '';
		$raw_css           = trim( $carousel_config['custom_css'] ?? '' );
		if ( $raw_css !== '' ) {
			$custom_css_output = sprintf(
				'<style id="wphz-carousel-css-%d">%s</style>',
				$carousel_id,
				$raw_css  // already sanitized via wp_strip_all_tags() on save.
			);
		}

		return $custom_css_output . TemplateLoader::render_return(
			'frontend/carousel-wrapper',
			array(
				'id'              => $carousel_id,
				'items'           => $items,
				'sound'           => ! empty( $carousel_config['mute'] ) ? 'mute' : 'unmute',
				'slide'           => $carousel_config['direction'] ?? 'ltr',
				'is_muted'        => ! empty( $carousel_config['mute'] ),
				'global_hide_atc' => (int) ( $carousel_config['hide_atc'] ?? 0 ),
			)
		);
	}

	/**
	 * Decode product_ids JSON and attach WC product objects to each item.
	 *
	 * @param  array<int, array> $items
	 * @return array<int, array>
	 */
	private function hydrate_items( array $items ): array {
		return array_map(
			function ( array $item ): array {
				$product_ids      = json_decode( $item['product_ids'], true ) ?: array();
				$item['products'] = $this->fetch_products( $product_ids );
				return $item;
			},
			$items
		);
	}

	/**
	 * Resolve WC product objects, filtering out non-visible / non-existent products.
	 *
	 * @param  array $product_ids
	 * @return array[] Associative array containing the WC_Product model and custom flags
	 */
	private function fetch_products( array $product_ids ): array {
		$products = array();
		foreach ( $product_ids as $product_data ) {
			$product_id = is_array( $product_data ) ? (int) ( $product_data['id'] ?? 0 ) : (int) $product_data;
			// Preserve null (inherit global) vs 0 (force show) vs 1 (force hide).
			$hide_atc = is_array( $product_data ) && array_key_exists( 'hide_atc', $product_data )
				? $product_data['hide_atc']
				: null;

			$product = wc_get_product( $product_id );
			if ( $product ) {
				$products[] = array(
					'model'    => $product,
					'hide_atc' => $hide_atc,
				);
			}
		}
		return $products;
	}
}
