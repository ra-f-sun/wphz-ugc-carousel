<?php
/**
 * Ajax handler for duplicating a carousel.
 *
 * @package WPHZ\UGCCarousels
 */

namespace WPHZ\UGCCarousels\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGCCarousels\AbstractSingleton;
use WPHZ\UGCCarousels\Helpers\NonceHelper;
use WPHZ\UGCCarousels\Repository\CarouselRepository;
use WPHZ\UGCCarousels\Repository\ItemRepository;

/**
 * DuplicateCarousel.
 */
class DuplicateCarousel extends AbstractSingleton {


	/**
	 * Register WordPress hooks for this component.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function init(): void {
		// We use admin-ajax.php but it's fundamentally a GET redirect hook.
		add_action( 'wp_ajax_ugcc_duplicate_carousel', array( $this, 'handle' ) );
	}

	/**
	 * Handle duplicate-carousel action — deep-clones carousel and all its items.
	 *
	 * Expects GET fields:
	 *  - nonce  string  WordPress nonce for 'ugcc_admin'.
	 *  - id     int     Source carousel ID to duplicate.
	 *
	 * @since  1.0.0
	 * @return void  Redirects to the new carousel's edit page and exits.
	 */
	public function handle(): void {
		NonceHelper::verify( 'ugcc_admin' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'ugc-carousels-for-woo' ) );
		}

		$carousel_id_input = filter_input( INPUT_GET, 'id', FILTER_VALIDATE_INT );
		$carousel_id       = is_int( $carousel_id_input ) ? $carousel_id_input : 0;
		if ( $carousel_id <= 0 ) {
			wp_die( esc_html__( 'Invalid Carousel ID.', 'ugc-carousels-for-woo' ) );
		}

		$carousel_repository = CarouselRepository::instance();
		$carousel            = $carousel_repository->get_by_id( $carousel_id );

		if ( ! $carousel ) {
			wp_die( esc_html__( 'Carousel not found.', 'ugc-carousels-for-woo' ) );
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
			wp_die( esc_html__( 'Failed to duplicate carousel.', 'ugc-carousels-for-woo' ) );
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
		wp_safe_redirect( admin_url( 'admin.php?page=ugc-carousels-for-woo&action=edit&id=' . $duplicate_carousel_id ) );
		exit;
	}
}
