<?php
/**
 * Frontend cart handling for the plugin.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGC\AbstractSingleton;

/**
 * CartHandler.
 */
class CartHandler extends AbstractSingleton {


	/**
	 * Register WordPress hooks for this component.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_wphz_ugc_add_to_cart', array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_wphz_ugc_add_to_cart', array( $this, 'handle' ) );
	}

	/**
	 * Handle add-to-cart AJAX request — available to both logged-in and guest users.
	 *
	 * Expects POST fields:
	 *  - nonce      string  WordPress nonce for 'wphz_ugc_atc'.
	 *  - product_id int     WooCommerce product ID to add.
	 *  - quantity   int     Optional. Quantity to add. Defaults to 1.
	 *
	 * @since  1.0.0
	 * @return void  Outputs JSON and exits.
	 */
	public function handle(): void {
		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! is_string( $nonce ) || '' === $nonce ) {
			$nonce = filter_input( INPUT_POST, 'wphz_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		}

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wphz_ugc_atc' ) ) {
			wp_send_json_error( array( 'message' => 'Nonce verification failed.' ), 403 );
		}

		$product_id_input = filter_input( INPUT_POST, 'product_id', FILTER_VALIDATE_INT );
		$product_id       = is_int( $product_id_input ) ? $product_id_input : 0;
		$quantity_input   = filter_input( INPUT_POST, 'quantity', FILTER_VALIDATE_INT );
		$quantity         = is_int( $quantity_input ) ? $quantity_input : 1;

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => 'Invalid product.' ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() ) {
			wp_send_json_error( array( 'message' => 'Product not purchasable.' ) );
		}

		$result = WC()->cart->add_to_cart( $product_id, $quantity );

		if ( $result ) {
			WC()->cart->calculate_totals();

			wp_send_json_success(
				array(
					'message'   => __( 'Added to cart.', 'wphz-ugc' ),
					'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', array() ),
					'cart_hash' => WC()->cart->get_cart_hash(),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'Could not add to cart.' ) );
		}
	}
}
