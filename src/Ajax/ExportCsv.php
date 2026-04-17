<?php
/**
 * Ajax handler for CSV export.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\NonceHelper;
use WPHZ\UGC\Repository\ItemRepository;

/**
 * ExportCsv.
 */
class ExportCsv extends AbstractSingleton {

	/**
	 * Register WordPress hooks for this component.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_wphz_ugc_export_csv', array( $this, 'handle' ) );
	}

	/**
	 * Handle CSV export action — streams a CSV file download response.
	 *
	 * Expects GET fields:
	 *  - nonce       string  WordPress nonce for 'wphz_ugc_admin'.
	 *  - carousel_id int     Carousel whose items should be exported.
	 *
	 * @since  1.0.0
	 * @return void  Outputs CSV headers + body and exits.
	 */
	public function handle(): void {
		NonceHelper::verify( 'wphz_ugc_admin' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized', '', array( 'response' => 403 ) );
		}

		$carousel_id_input = filter_input( INPUT_GET, 'carousel_id', FILTER_VALIDATE_INT );
		$carousel_id       = is_int( $carousel_id_input ) ? $carousel_id_input : 0;
		if ( $carousel_id <= 0 ) {
			wp_die( 'Invalid carousel ID.' );
		}

		$items    = ItemRepository::instance()->get_all( (string) $carousel_id );
		$filename = 'carousel-' . $carousel_id . '-' . gmdate( 'Y-m-d' ) . '.csv';

		// Clear any buffered output before sending file headers.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$csv = new \SplTempFileObject();
		$csv->fputcsv( array( 'sort_order', 'video_url_hd', 'video_url_sd', 'poster_url', 'products' ) );

		foreach ( $items as $item ) {
			$pids_raw = json_decode( $item['product_ids'], true );
			if ( ! is_array( $pids_raw ) ) {
				$pids_raw = array();
			}

			$products_export = array();

			foreach ( $pids_raw as $product_id_raw ) {
				$product_id = (int) ( $product_id_raw['id'] ?? 0 );
				$product    = $product_id ? wc_get_product( $product_id ) : null;
				$sku        = $product ? $product->get_sku() : '';

				// hide_atc: null = inherit, 0 = force show, 1 = force hide.
				$hide_atc = array_key_exists( 'hide_atc', $product_id_raw ) ? $product_id_raw['hide_atc'] : null;

				$products_export[] = array(
					'sku'      => $sku,
					'id'       => $product_id,
					'hide_atc' => $hide_atc,
				);
			}

			$csv->fputcsv(
				array(
					$item['sort_order'],
					$item['video_url_hd'] ?? '',
					$item['video_url_sd'] ?? '',
					$item['poster_url'] ?? '',
					wp_json_encode( $products_export ),
				)
			);
		}

		$csv->rewind();
		while ( ! $csv->eof() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download stream must remain raw.
			echo $csv->fgets();
		}

		exit;
	}
}
