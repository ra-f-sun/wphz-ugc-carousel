<?php
/**
 * Ajax product search handler.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\NonceHelper;
use WPHZ\UGC\Helpers\TransientHelper;

/**
 * ProductSearch.
 */
class ProductSearch extends AbstractSingleton {


	/**
	 * Register WordPress hooks for this component.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_wphz_ugc_product_search', array( $this, 'handle' ) );
	}

	/**
	 * Handle product search AJAX request — returns matching WooCommerce products.
	 *
	 * Expects GET fields:
	 *  - nonce  string  WordPress nonce for 'wphz_ugc_admin'.
	 *  - term   string  Search term (minimum 2 characters). Matched against SKU, name, and ID.
	 *
	 * @since  1.0.0
	 * @return void  Outputs JSON array of product objects and exits.
	 */
	public function handle(): void {
		$nonce = filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! is_string( $nonce ) || '' === $nonce ) {
			$nonce = filter_input( INPUT_GET, 'wphz_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		}

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wphz_ugc_admin' ) ) {
			wp_send_json_error( array( 'message' => 'Nonce verification failed.' ), 403 );
		}

		NonceHelper::verify( 'wphz_ugc_admin' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$term = sanitize_text_field(
			filter_input( INPUT_GET, 'term', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ?? ''
		);

		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( array() );
		}

		// 'v2_' prefix busts caches from the old format (no status/sku fields).
		$key    = TransientHelper::product_key( 'v2_' . $term );
		$cached = TransientHelper::get( $key );

		if ( null !== $cached ) {
			wp_send_json_success( $cached );
		}

		global $wpdb;

		$ordered_ids = array();

		// 1. Exact SKU match (case-insensitive).
		$exact_sku_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = '_sku' AND LOWER(meta_value) = LOWER(%s)
                 LIMIT 5",
				$term
			)
		);
		foreach ( $exact_sku_ids as $id ) {
			$ordered_ids[] = (int) $id;
		}

		// 2. Partial SKU match.
		$partial_sku_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = '_sku' AND LOWER(meta_value) LIKE LOWER(%s)
                 LIMIT 10",
				'%' . $wpdb->esc_like( $term ) . '%'
			)
		);
		foreach ( $partial_sku_ids as $id ) {
			$id = (int) $id;
			if ( ! in_array( $id, $ordered_ids, true ) ) {
				$ordered_ids[] = $id;
			}
		}

		// 3. Name search â€” include all statuses so drafts/private products are found.
		$name_query = new \WC_Product_Query(
			array(
				'limit'   => 10,
				's'       => $term,
				'orderby' => 'relevance',
				'return'  => 'ids',
				'status'  => array( 'publish', 'draft', 'private', 'pending' ),
			)
		);
		foreach ( $name_query->get_products() as $id ) {
			$id = (int) $id;
			if ( ! in_array( $id, $ordered_ids, true ) ) {
				$ordered_ids[] = $id;
			}
		}

		// 4. Numeric ID lookup.
		if ( is_numeric( $term ) ) {
			$numeric_id = (int) $term;
			if ( ! in_array( $numeric_id, $ordered_ids, true ) ) {
				$ordered_ids[] = $numeric_id;
			}
		}

		// Build result set â€” no publish-only filter; show all valid products with status info.
		$results = array();
		foreach ( array_slice( $ordered_ids, 0, 15 ) as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$results[] = array(
				'id'                 => $product->get_id(),
				'name'               => $product->get_name(),
				'sku'                => $product->get_sku(),
				'price_html'         => wp_strip_all_tags( \WPHZ\UGC\Helpers\PriceHelper::get_clean_price( $product ) ),
				'thumbnail'          => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
				'status'             => $product->get_status(),
				'catalog_visibility' => $product->get_catalog_visibility(),
			);
		}

		TransientHelper::set( $key, $results );
		wp_send_json_success( $results );
	}
}
