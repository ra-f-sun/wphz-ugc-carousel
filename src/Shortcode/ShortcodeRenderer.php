<?php
/**
 * Frontend shortcode rendering for the plugin.
 *
 * @package WPHZ\UGCCarousels
 */

namespace WPHZ\UGCCarousels\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGCCarousels\AbstractSingleton;
use WPHZ\UGCCarousels\Repository\CarouselRepository;
use WPHZ\UGCCarousels\Repository\ItemRepository;
use WPHZ\UGCCarousels\Helpers\TemplateLoader;
use WPHZ\UGCCarousels\Frontend\AssetLoader;

/**
 * ShortcodeRenderer.
 */
class ShortcodeRenderer extends AbstractSingleton {


	/**
	 * Main shortcode callback — returns rendered HTML string (never echoes).
	 *
	 * @since  1.0.0
	 * @param  array<string, string>|string $atts Shortcode attributes. Accepts: id (int).
	 * @return string  Rendered carousel HTML, or an error notice string.
	 */
	public function render( array|string $atts ): string {
		$atts        = shortcode_atts( array( 'id' => 0 ), (array) $atts, 'wphz_ugc_carousel' );
		$carousel_id = (int) $atts['id'];

		if ( $carousel_id <= 0 ) {
			return '<p>UGC Carousel: Please provide a valid carousel ID in the shortcode.</p>';
		}

		$carousel_config = CarouselRepository::instance()->get_by_id( $carousel_id );
		if ( ! $carousel_config ) {
			return sprintf( '<p>UGC Carousel: Carousel #%d not found.</p>', $carousel_id );
		}

		$items = ItemRepository::instance()->get_all( (string) $carousel_id );
		$items = $this->hydrate_items( $items );

		if ( empty( $items ) ) {
			return '';
		}

		// Lazy-enqueue frontend assets — AssetLoader receives the unified config array.
		AssetLoader::instance()->enqueue_now( $carousel_config );

		// Prepend scoped custom CSS if the carousel has any.
		// wp_add_inline_style() cannot be used here because shortcodes render.
		// during the_content(), which is after wp_head() has already fired.
		// A <style> tag inline in the output is the correct approach.
		$custom_css_output = '';
		$raw_css           = trim( $carousel_config['custom_css'] ?? '' );
		if ( '' !== $raw_css ) {
			$custom_css_output = sprintf(
				'<style id="ugcc-carousel-css-%d">%s</style>',
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
	 * Decode product_ids JSON and attach resolved WC product objects to each item.
	 *
	 * @since  1.0.0
	 * @param  array<int, array> $items Raw carousel items from the repository.
	 * @return array<int, array>  Items with a `products` key containing hydrated data.
	 */
	private function hydrate_items( array $items ): array {
		return array_map(
			function ( array $item ): array {
				$product_ids = json_decode( $item['product_ids'], true );
				if ( ! is_array( $product_ids ) ) {
					$product_ids = array();
				}
				$item['products'] = $this->fetch_products( $product_ids );
				return $item;
			},
			$items
		);
	}

	/**
	 * Resolve WC product objects, skipping products that no longer exist.
	 *
	 * @since  1.0.0
	 * @param  array $product_ids Array of product entries with 'id' and optional 'hide_atc' keys.
	 * @return array<int, array{model: \WC_Product, hide_atc: int|null}>
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
