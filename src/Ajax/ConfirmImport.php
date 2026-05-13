<?php
/**
 * Ajax handler for Phase 2 of the two-phase CSV import — save confirmed product selections.
 *
 * @package WPHZ\UGCCarousels
 */

namespace WPHZ\UGCCarousels\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGCCarousels\AbstractSingleton;
use WPHZ\UGCCarousels\Repository\ItemRepository;

/**
 * ConfirmImport.
 *
 * Phase 2 of the two-phase CSV import. Receives the user-confirmed product
 * selections from the frontend modal and persists them to the database.
 */
class ConfirmImport extends AbstractSingleton {

	/**
	 * Register WordPress hooks for this component.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_ugcc_confirm_import', array( $this, 'handle' ) );
	}

	/**
	 * Handle Phase 2 confirm-import request.
	 *
	 * Expects POST fields:
	 *  - nonce       string  WordPress nonce for 'ugcc_admin'.
	 *  - carousel_id int     Target carousel ID.
	 *  - items       string  JSON-encoded array of confirmed rows:
	 *                        [{video_url_hd, video_url_sd, poster_url, products: [{id, hide_atc}]}]
	 *
	 * @since  1.0.0
	 * @return void  Outputs JSON and exits.
	 */
	public function handle(): void {
		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS )
			?? filter_input( INPUT_POST, 'ugcc_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS )
			?? '';

		if ( ! wp_verify_nonce( $nonce, 'ugcc_admin' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'ugc-carousels-for-woo' ) ), 403 );
			return;
		}

		$carousel_id = (int) filter_input( INPUT_POST, 'carousel_id', FILTER_VALIDATE_INT );
		if ( $carousel_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid carousel ID.', 'ugc-carousels-for-woo' ) ) );
			return;
		}

		$items_raw = filter_input( INPUT_POST, 'items', FILTER_DEFAULT );
		$items     = json_decode( wp_unslash( (string) $items_raw ), true );
		if ( ! is_array( $items ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid items data.', 'ugc-carousels-for-woo' ) ) );
			return;
		}

		$repo = ItemRepository::instance();
		$repo->delete_by_carousel( $carousel_id );

		$sort     = 0;
		$imported = 0;
		$errors   = array();

		foreach ( $items as $item ) {
			$video_url_hd = esc_url_raw( $item['video_url_hd'] ?? '' );
			$video_url_sd = esc_url_raw( $item['video_url_sd'] ?? '' );
			$poster_url   = esc_url_raw( $item['poster_url'] ?? '' );

			if ( ! $video_url_hd && ! $video_url_sd ) {
				continue;
			}

			$product_ids = array();
			foreach ( (array) ( $item['products'] ?? array() ) as $product_entry ) {
				$product_id = (int) ( $product_entry['id'] ?? 0 );
				$hide_atc   = array_key_exists( 'hide_atc', $product_entry ) ? $product_entry['hide_atc'] : null;
				if ( $product_id > 0 ) {
					$product_ids[] = array(
						'id'       => $product_id,
						'hide_atc' => $hide_atc,
					);
				}
			}

			$new_id = $repo->insert(
				array(
					'carousel_id'  => (string) $carousel_id,
					'sort_order'   => $sort++,
					'video_id'     => 0,
					'video_url_hd' => $video_url_hd,
					'video_url_sd' => $video_url_sd,
					'poster_url'   => $poster_url,
					'product_ids'  => $product_ids,
				)
			);

			if ( $new_id ) {
				++$imported;
			} else {
				$errors[] = 'Row ' . $sort . ': database insert failed.';
			}
		}

		wp_send_json_success(
			array(
				'imported' => $imported,
				'errors'   => $errors,
			)
		);
	}
}
