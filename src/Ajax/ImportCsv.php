<?php
/**
 * Ajax handler for importing CSV data.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Repository\ItemRepository;

/**
 * ImportCsv.
 */
class ImportCsv extends AbstractSingleton {

	/**
	 * Register WordPress hooks for this component.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_wphz_ugc_import_csv', array( $this, 'handle' ) );
	}

	/**
	 * Handle CSV import action — replaces all carousel items from an uploaded CSV.
	 *
	 * Expects POST fields:
	 *  - nonce       string  WordPress nonce for 'wphz_ugc_admin'.
	 *  - carousel_id int     Target carousel to import into.
	 *
	 * Expects FILES:
	 *  - csv_file    file    A UTF-8 CSV with columns: sort_order, video_url_hd, video_url_sd, poster_url, products.
	 *
	 * @since  1.0.0
	 * @return void  Outputs JSON and exits.
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

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES values validated below before use.
		if ( empty( $_FILES['csv_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => 'No valid file uploaded.' ) );
		}

		$file_name = sanitize_file_name( (string) ( $_FILES['csv_file']['name'] ?? '' ) );
		$extension = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
		if ( 'csv' !== $extension ) {
			wp_send_json_error( array( 'message' => __( 'File must have a .csv extension.', 'wphz-ugc-carousel' ) ) );
		}

		$allowed_mime_types = array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' );
		$uploaded_type      = (string) ( $_FILES['csv_file']['type'] ?? '' );
		if ( ! in_array( $uploaded_type, $allowed_mime_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type. Please upload a CSV file.', 'wphz-ugc-carousel' ) ) );
		}
		// phpcs:enable

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
		ItemRepository::instance()->delete_by_carousel( $carousel_id );

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
