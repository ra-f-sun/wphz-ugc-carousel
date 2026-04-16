<?php
/**
 * Ajax handler for importing CSV data.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Ajax;

defined( 'ABSPATH' ) || exit;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Repository\ItemRepository;

/**
 * ImportCsv.
 */
class ImportCsv extends AbstractSingleton {

	/**
	 * Initialize hooks.
	 *
	 * @return void Return value.
	 */
	public function init(): void {
		add_action( 'wp_ajax_wphz_ugc_import_csv', array( $this, 'handle' ) );
	}

	/**
	 * Handle CSV import action.
	 *
	 * @return void Return value.
	 */
	public function handle(): void {
		$nonce = filter_input( INPUT_POST, 'wphz_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! is_string( $nonce ) || '' === $nonce ) {
			$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		}

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wphz_ugc_admin' ) ) {
			wp_send_json_error( array( 'message' => 'Nonce verification failed.' ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$carousel_id_input = filter_input( INPUT_POST, 'carousel_id', FILTER_VALIDATE_INT );
		$carousel_id       = is_int( $carousel_id_input ) ? $carousel_id_input : 0;
		if ( $carousel_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid carousel ID.' ) );
		}

		if ( empty( $_FILES['csv_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => 'No valid file uploaded.' ) );
		}

		$csv_path = (string) $_FILES['csv_file']['tmp_name'];
		try {
			$csv = new \SplFileObject( $csv_path, 'r' );
		} catch ( \RuntimeException $exception ) {
			wp_send_json_error( array( 'message' => 'Cannot read uploaded file.' ) );
		}

		$header   = $csv->fgetcsv();
		$expected = array( 'sort_order', 'video_url_hd', 'video_url_sd', 'poster_url', 'products' );
		if ( $header !== $expected ) {
			wp_send_json_error(
				array(
					'message' => 'Invalid CSV format. Expected columns: ' . implode( ', ', $expected ),
				)
			);
		}

		// Delete all existing items for this carousel (same pattern as SaveContent).
		global $wpdb;
		$table = $wpdb->prefix . 'wphz_ugc_items';
		$wpdb->delete( $table, array( 'carousel_id' => (string) $carousel_id ) );

		$repo     = ItemRepository::instance();
		$sort     = 0;
		$imported = 0;
		$errors   = array();

		while ( ! $csv->eof() ) {
			$row = $csv->fgetcsv();
			if ( false === $row || array( null ) === $row ) {
				continue;
			}

			// Pad to 5 columns in case products column is missing.
			$row = array_pad( $row, 5, '' );

			[, $video_url_hd, $video_url_sd, $poster_url, $products_json] = $row;

			$video_url_hd = esc_url_raw( trim( $video_url_hd ) );
			$video_url_sd = esc_url_raw( trim( $video_url_sd ) );
			$poster_url   = esc_url_raw( trim( $poster_url ) );

			// Skip rows with no video at all.
			if ( ! $video_url_hd && ! $video_url_sd ) {
				continue;
			}

			$product_ids = array();
			$row_errors  = array();

			if ( ! empty( trim( $products_json ) ) ) {
				$parsed = json_decode( trim( $products_json ), true );
				if ( is_array( $parsed ) ) {
					foreach ( $parsed as $entry ) {
						$sku     = trim( $entry['sku'] ?? '' );
						$id_hint = (int) ( $entry['id'] ?? 0 );

						// Prefer SKU lookup; fall back to ID for products with no SKU.
						$resolved_id = 0;
						if ( '' !== $sku ) {
							$resolved_id = (int) wc_get_product_id_by_sku( $sku );
						}
						if ( ! $resolved_id && $id_hint > 0 ) {
							$resolved_id = $id_hint;
						}

						if ( ! $resolved_id ) {
							$row_errors[] = 'Product SKU "' . esc_html( $sku ) . '" (id hint: ' . $id_hint . ') not found, skipped.';
							continue;
						}

						// hide_atc: null = inherit, 0 = force show, 1 = force hide.
						$hide_raw = array_key_exists( 'hide_atc', $entry ) ? $entry['hide_atc'] : null;
						$hide_atc = ( null !== $hide_raw ) ? (int) $hide_raw : null;

						$product_ids[] = array(
							'id'       => $resolved_id,
							'hide_atc' => $hide_atc,
						);
					}
				}
			}

			if ( ! empty( $row_errors ) ) {
				foreach ( $row_errors as $err ) {
					$errors[] = 'Row ' . ( $sort + 1 ) . ': ' . $err;
				}
			}

			if ( $repo->insert(
				array(
					'carousel_id'  => (string) $carousel_id,
					'sort_order'   => $sort++,
					'video_id'     => 0,
					'video_url_hd' => $video_url_hd,
					'video_url_sd' => $video_url_sd,
					'poster_url'   => $poster_url,
					'product_ids'  => $product_ids,
				)
			) ) {
				++$imported;
			} else {
				$errors[] = 'Row ' . $sort . ': database insert failed.';
			}
		}

		wp_send_json_success(
			array(
				'message'  => sprintf( '%d item(s) imported successfully.', $imported ),
				'imported' => $imported,
				'errors'   => $errors,
			)
		);
	}
}
