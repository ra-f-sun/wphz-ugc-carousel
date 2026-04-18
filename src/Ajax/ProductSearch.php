<?php
/**
 * Ajax product search handler.
 *
 * @package WPHZ\UGCCarousels
 */

namespace WPHZ\UGCCarousels\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGCCarousels\AbstractSingleton;
use WPHZ\UGCCarousels\Helpers\NonceHelper;
use WPHZ\UGCCarousels\Helpers\TransientHelper;

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
		add_action( 'wp_ajax_ugcc_product_search', array( $this, 'handle' ) );
	}

	/**
	 * Handle product search AJAX request — returns matching WooCommerce products.
	 *
	 * Expects GET fields:
	 *  - nonce  string  WordPress nonce for 'ugcc_admin'.
	 *  - term   string  Search term (minimum 2 characters). Matched against SKU, name, and ID.
	 *
	 * @since  1.0.0
	 * @return void  Outputs JSON array of product objects and exits.
	 */
	public function handle(): void {
		$nonce = filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! is_string( $nonce ) || '' === $nonce ) {
			$nonce = filter_input( INPUT_GET, 'ugcc_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		}

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'ugcc_admin' ) ) {
			wp_send_json_error( array( 'message' => 'Nonce verification failed.' ), 403 );
		}

		NonceHelper::verify( 'ugcc_admin' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$term = sanitize_text_field(
			filter_input( INPUT_GET, 'term', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ?? ''
		);

		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( array() );
		}

		$key    = TransientHelper::product_key( 'v2_' . $term );
		$cached = TransientHelper::get( $key );

		if ( null !== $cached ) {
			wp_send_json_success( $cached );
		}

		global $wpdb;

		$ordered_ids = array();

		// Exact SKU match (case-insensitive).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP_Query cannot filter postmeta by meta_key='_sku' with the required exact-then-partial ordering. The full result set is cached via TransientHelper (5-minute TTL) immediately after all queries complete.
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

		// Partial SKU match.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Same reason as exact SKU match above; postmeta must be queried directly for _sku filtering. Cached together with the full result set via TransientHelper.
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

		// Name search — include all statuses so drafts/private products are found.
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

		// Numeric ID lookup.
		if ( is_numeric( $term ) ) {
			$numeric_id = (int) $term;
			if ( ! in_array( $numeric_id, $ordered_ids, true ) ) {
				$ordered_ids[] = $numeric_id;
			}
		}

		// Build result set — no publish-only filter; show all valid products with status info.
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
				'price_html'         => wp_strip_all_tags( \WPHZ\UGCCarousels\Helpers\PriceHelper::get_clean_price( $product ) ),
				'thumbnail'          => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
				'status'             => $product->get_status(),
				'catalog_visibility' => $product->get_catalog_visibility(),
			);
		}

		TransientHelper::set( $key, $results );
		wp_send_json_success( $results );
	}
}
