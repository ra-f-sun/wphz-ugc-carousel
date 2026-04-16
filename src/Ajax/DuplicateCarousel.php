<?php
/**
 * Ajax handler for duplicating a carousel.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\NonceHelper;
use WPHZ\UGC\Repository\CarouselRepository;
use WPHZ\UGC\Repository\ItemRepository;

/**
 * DuplicateCarousel.
 */
class DuplicateCarousel extends AbstractSingleton {


	/**
	 * Initialize hooks.
	 *
	 * @return void Return value.
	 */
	public function init(): void {
		// We use admin-ajax.php but it's fundamentally a GET redirect hook.
		add_action( 'wp_ajax_wphz_ugc_duplicate_carousel', array( $this, 'handle' ) );
	}

	/**
	 * Handle duplicate carousel action.
	 *
	 * @return void Return value.
	 */
	public function handle(): void {
		NonceHelper::verify( 'wphz_ugc_admin' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'wphz-ugc' ) );
		}

		$carousel_id_input = filter_input( INPUT_GET, 'id', FILTER_VALIDATE_INT );
		$carousel_id       = is_int( $carousel_id_input ) ? $carousel_id_input : 0;
		if ( $carousel_id <= 0 ) {
			wp_die( esc_html__( 'Invalid Carousel ID.', 'wphz-ugc' ) );
		}

		$carousel_repository = CarouselRepository::instance();
		$carousel            = $carousel_repository->get_by_id( $carousel_id );

		if ( ! $carousel ) {
			wp_die( esc_html__( 'Carousel not found.', 'wphz-ugc' ) );
		}

		// Duplicate the parent carousel configuration exactly.
		$duplicate_carousel_id = $carousel_repository->insert(
			array(
				'name'           => $carousel['name'] . ' (duplicate)',
				'heading'        => $carousel['heading'],
				'subheading'     => $carousel['subheading'],
				'mute'           => $carousel['mute'],
				'direction'      => $carousel['direction'],
				'on_arrow_right' => $carousel['on_arrow_right'],
				'on_arrow_left'  => $carousel['on_arrow_left'],
				'custom_css'     => $carousel['custom_css'],
			)
		);

		if ( ! $duplicate_carousel_id ) {
			wp_die( esc_html__( 'Failed to duplicate carousel.', 'wphz-ugc' ) );
		}

		// Deep clone the nested video/product item arrays.
		$item_repository = ItemRepository::instance();
		$items           = $item_repository->get_all( (string) $carousel_id );

		foreach ( $items as $item ) {
			// Data maps back cleanly because product_ids encodes into a JSON string natively within get_all.
			$product_ids = json_decode( $item['product_ids'], true );
			if ( ! is_array( $product_ids ) ) {
				$product_ids = array();
			}

			$item_repository->insert(
				array(
					'carousel_id'  => (string) $duplicate_carousel_id, // Map it cleanly back directly to the new parent.
					'sort_order'   => $item['sort_order'],
					'video_id'     => $item['video_id'],
					'video_url_hd' => $item['video_url_hd'],
					'video_url_sd' => $item['video_url_sd'],
					'poster_url'   => $item['poster_url'] ?? '',
					'product_ids'  => $product_ids,
				)
			);
		}

		// Redirect immediately seamlessly into the newly cloned configuration environment.
		wp_safe_redirect( admin_url( 'admin.php?page=wphz-ugc-carousel&action=edit&id=' . $duplicate_carousel_id ) );
		exit;
	}
}
