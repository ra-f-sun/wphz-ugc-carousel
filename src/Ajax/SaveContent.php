<?php
/**
 * Ajax handler for saving carousel content.
 *
 * @package WPHZ\UGCCarousels
 */

namespace WPHZ\UGCCarousels\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGCCarousels\AbstractSingleton;
use WPHZ\UGCCarousels\Installer\Installer;
use WPHZ\UGCCarousels\Repository\ItemRepository;

/**
 * SaveContent.
 */
class SaveContent extends AbstractSingleton {


	/**
	 * Register WordPress hooks for this component.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_ugcc_save_content', array( $this, 'handle' ) );
	}

	/**
	 * Handle save-content AJAX request — replaces all items for a carousel.
	 *
	 * Expects POST fields:
	 *  - nonce       string  WordPress nonce for 'ugcc_admin'.
	 *  - carousel_id int     Carousel to update.
	 *  - items       array   Serialized item rows (video_url_hd, video_url_sd, poster_url, products[]).
	 *
	 * @since  1.0.0
	 * @return void  Outputs JSON and exits.
	 */
	public function handle(): void {
		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! is_string( $nonce ) || '' === $nonce ) {
			$nonce = filter_input( INPUT_POST, 'ugcc_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		}

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'ugcc_admin' ) ) {
			wp_send_json_error( array( 'message' => 'Nonce verification failed.' ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$items_raw_input = filter_input( INPUT_POST, 'items', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$items_raw       = is_array( $items_raw_input ) ? $items_raw_input : array();

		$carousel_id_input = filter_input( INPUT_POST, 'carousel_id', FILTER_VALIDATE_INT );
		$carousel_id       = is_int( $carousel_id_input ) ? $carousel_id_input : 0;

		if ( $carousel_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid Carousel ID.', 'ugc-carousels-for-woo' ) ) );
		}

		if ( ! Installer::items_has_poster_column() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Database schema is outdated. Please reload the page and try again.', 'ugc-carousels-for-woo' ),
				)
			);
		}

		$item_repository = ItemRepository::instance();

		// Delete-all then re-insert: simple replace-all strategy scoped to parent ID.
		$this->clear_existing( $carousel_id );

		$sort = 0;
		foreach ( $items_raw as $data ) {
			$video_id     = (int) ( $data['video_id'] ?? 0 );
			$video_url_hd = esc_url_raw( $data['video_url_hd'] ?? '' );
			$video_url_sd = esc_url_raw( $data['video_url_sd'] ?? '' );
			$poster_url   = esc_url_raw( $data['poster_url'] ?? '' );

			$products_raw = (array) ( $data['products'] ?? array() );
			$product_ids  = array();
			foreach ( $products_raw as $product_data ) {
				if ( empty( $product_data['id'] ) ) {
					continue;
				}
				// 3-state: null = inherit global, 0 = force show, 1 = force hide.
				// Empty string means "inherit" (sent by the select "Use global default" option).
				$hide_raw      = $product_data['hide_atc'] ?? '';
				$product_ids[] = array(
					'id'       => (int) $product_data['id'],
					'hide_atc' => ( '' !== $hide_raw && null !== $hide_raw ) ? (int) $hide_raw : null,
				);
			}

			// Skip row if both URLs are missing.
			// If one is missing, it will be skipped by the frontend dynamic engine fallback.
			if ( ! $video_url_hd && ! $video_url_sd ) {
				continue;
			}

			if ( ! $item_repository->insert(
				array(
					'carousel_id'  => (string) $carousel_id,
					'sort_order'   => $sort++,
					'video_id'     => $video_id,
					'video_url_hd' => $video_url_hd,
					'video_url_sd' => $video_url_sd,
					'poster_url'   => $poster_url,
					'product_ids'  => $product_ids,
				)
			) ) {
				global $wpdb;
				wp_send_json_error(
					array(
						'message' => __( 'Failed to save carousel content. Please retry after refreshing the page.', 'ugc-carousels-for-woo' ),
						'debug'   => $wpdb->last_error,
					)
				);
			}
		}

		wp_send_json_success(
			array(
				'message'   => __( 'Content saved.', 'ugc-carousels-for-woo' ),
				'shortcode' => sprintf( '[wphz_ugc_carousel id="%d"]', $carousel_id ),
			)
		);
	}

	/**
	 * Delete all existing items for a carousel before re-inserting.
	 *
	 * @since  1.0.0
	 * @param  int $carousel_id Parent carousel ID.
	 * @return void
	 */
	private function clear_existing( int $carousel_id ): void {
		ItemRepository::instance()->delete_by_carousel( $carousel_id );
	}
}
