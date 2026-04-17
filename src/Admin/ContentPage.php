<?php
/**
 * Content page data provider.
 *
 * @package WPHZ\UGCCarousels
 */

namespace WPHZ\UGCCarousels\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGCCarousels\AbstractSingleton;
use WPHZ\UGCCarousels\Repository\ItemRepository;

/**
 * ContentPage.
 */
class ContentPage extends AbstractSingleton {

	/**
	 * Build the data array passed to the content-tab template.
	 *
	 * @since  1.0.0
	 * @param  int $id Carousel ID.
	 * @return array<string, mixed>
	 */
	public function get_data( int $id ): array {
		$items = ItemRepository::instance()->get_all( (string) $id );

		// Decode product_ids JSON for each item (C2 contract: always JSON, never comma-sep).
		$items = array_map(
			function ( array $item ): array {
				$decoded = json_decode( $item['product_ids'], true );
				if ( ! is_array( $decoded ) ) {
					$decoded = array();
				}

				$item['product_ids'] = $decoded;
				return $item;
			},
			$items
		);

		return array(
			'id'        => $id,
			'items'     => $items,
			'shortcode' => sprintf( '[wphz_ugc_carousel id="%d"]', $id ),
		);
	}
}
