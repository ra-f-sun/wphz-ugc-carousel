<?php
/**
 * Ajax handler for Phase 1 of the two-phase CSV import — parse and resolve products.
 *
 * @package WPHZ\UGCCarousels
 */

namespace WPHZ\UGCCarousels\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct postmeta query is required to find ALL products matching a SKU (including all variations). wc_get_product_id_by_sku() returns only one ID. NoCaching is intentional: this is a one-time admin import action, not a repeated frontend query.

use WPHZ\UGCCarousels\AbstractSingleton;

/**
 * ParseCsv.
 *
 * Phase 1 of the two-phase CSV import. Parses an uploaded CSV and resolves
 * ALL matching products for each SKU via a direct postmeta query. Returns
 * match findings to the frontend without writing to the database.
 */
class ParseCsv extends AbstractSingleton {

	/**
	 * Register WordPress hooks for this component.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_ugcc_parse_csv', array( $this, 'handle' ) );
	}

	/**
	 * Handle Phase 1 CSV parse request.
	 *
	 * Expects POST fields:
	 *  - nonce       string  WordPress nonce for 'ugcc_admin'.
	 *  - carousel_id int     Target carousel ID (for context only; not written to DB).
	 *  - csv_file    file    Uploaded CSV file.
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

		// Validate uploaded file.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is validated below via is_uploaded_file and MIME/extension checks.
		$tmp_name = isset( $_FILES['csv_file']['tmp_name'] ) ? (string) $_FILES['csv_file']['tmp_name'] : '';
		if ( ! $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'ugc-carousels-for-woo' ) ) );
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file_name = isset( $_FILES['csv_file']['name'] ) ? sanitize_file_name( (string) $_FILES['csv_file']['name'] ) : '';
		$extension = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
		if ( 'csv' !== $extension ) {
			wp_send_json_error( array( 'message' => __( 'File must have a .csv extension.', 'ugc-carousels-for-woo' ) ) );
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$uploaded_type      = isset( $_FILES['csv_file']['type'] ) ? (string) $_FILES['csv_file']['type'] : '';
		$allowed_mime_types = array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' );
		if ( ! in_array( $uploaded_type, $allowed_mime_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type. Please upload a CSV file.', 'ugc-carousels-for-woo' ) ) );
			return;
		}

		try {
			$csv = new \SplFileObject( $tmp_name, 'r' );
			$csv->setFlags( \SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::READ_AHEAD );
		} catch ( \RuntimeException ) {
			wp_send_json_error( array( 'message' => __( 'Could not open uploaded file.', 'ugc-carousels-for-woo' ) ) );
			return;
		}

		// Validate header row.
		$header = $csv->current();
		if ( ! is_array( $header ) ) {
			wp_send_json_error( array( 'message' => __( 'CSV file appears to be empty.', 'ugc-carousels-for-woo' ) ) );
			return;
		}
		$expected_headers = array( 'sort_order', 'video_url_hd', 'video_url_sd', 'poster_url', 'products' );
		foreach ( $expected_headers as $i => $col ) {
			if ( ! isset( $header[ $i ] ) || trim( $header[ $i ] ) !== $col ) {
				wp_send_json_error( array( 'message' => __( 'Invalid CSV format. Please export a fresh CSV and re-import.', 'ugc-carousels-for-woo' ) ) );
				return;
			}
		}

		$csv->next();
		$rows = array();

		while ( ! $csv->eof() ) {
			$row = $csv->current();
			$csv->next();

			if ( ! is_array( $row ) || count( $row ) < 5 ) {
				continue;
			}

			[ , $video_url_hd, $video_url_sd, $poster_url, $products_json ] = array_pad( $row, 5, '' );

			$products_parsed = json_decode( trim( $products_json ), true );
			if ( ! is_array( $products_parsed ) ) {
				$products_parsed = array();
			}

			$products_result = array();
			foreach ( $products_parsed as $entry ) {
				$sku      = trim( $entry['sku'] ?? '' );
				$id_hint  = (int) ( $entry['id'] ?? 0 );
				$hide_atc = array_key_exists( 'hide_atc', $entry ) ? $entry['hide_atc'] : null;

				$match_ids = array();
				if ( '' !== $sku ) {
					global $wpdb;
					$match_ids = (array) $wpdb->get_col(
						$wpdb->prepare(
							// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpdb->postmeta is a WP core table reference, not user input.
							"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s",
							$sku
						)
					);
				}

				// Fall back to ID hint only when SKU yields no matches.
				if ( empty( $match_ids ) && $id_hint > 0 ) {
					$match_ids = array( $id_hint );
				}

				$matches = array();
				foreach ( $match_ids as $match_id ) {
					$product = wc_get_product( (int) $match_id );
					if ( ! $product ) {
						continue;
					}

					$var_attrs = array();
					if ( $product->is_type( 'variation' ) ) {
						foreach ( $product->get_variation_attributes() as $attr_key => $attr_val ) {
							$clean                          = str_replace( array( 'attribute_pa_', 'attribute_' ), '', $attr_key );
							$var_attrs[ ucfirst( $clean ) ] = ucfirst( $attr_val );
						}
					}

					$matches[] = array(
						'id'                   => $product->get_id(),
						'name'                 => $product->get_name(),
						'sku'                  => $product->get_sku(),
						'type'                 => $product->get_type(),
						'variation_attributes' => ! empty( $var_attrs ) ? $var_attrs : null,
						'catalog_visibility'   => $product->get_catalog_visibility(),
						'status'               => $product->get_status(),
						'hide_atc'             => $hide_atc,
					);
				}

				$products_result[] = array(
					'exported' => array(
						'sku'                => $entry['sku'] ?? '',
						'id'                 => $id_hint,
						'name'               => $entry['name'] ?? '',
						'variation'          => $entry['variation'] ?? null,
						'catalog_visibility' => $entry['catalog_visibility'] ?? '',
						'hide_atc'           => $hide_atc,
					),
					'matches'  => $matches,
					'missing'  => empty( $matches ),
				);
			}

			$rows[] = array(
				'video_url_hd' => esc_url_raw( trim( $video_url_hd ) ),
				'video_url_sd' => esc_url_raw( trim( $video_url_sd ) ),
				'poster_url'   => esc_url_raw( trim( $poster_url ) ),
				'products'     => $products_result,
			);
		}

		wp_send_json_success( array( 'rows' => $rows ) );
	}
}
