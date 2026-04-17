<?php
/**
 * Ajax handler for deleting an item.
 *
 * @package WPHZ\UGCCarousels
 */

namespace WPHZ\UGCCarousels\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGCCarousels\AbstractSingleton;
use WPHZ\UGCCarousels\Helpers\NonceHelper;
use WPHZ\UGCCarousels\Repository\ItemRepository;

/**
 * DeleteItem.
 */
class DeleteItem extends AbstractSingleton {


	/**
	 * Register WordPress hooks for this component.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_ugcc_delete_item', array( $this, 'handle' ) );
	}

	/**
	 * Handle delete-item AJAX request.
	 *
	 * Expects POST fields:
	 *  - nonce  string  WordPress nonce for 'ugcc_admin'.
	 *  - id     int     Item ID to delete.
	 *
	 * @since  1.0.0
	 * @return void  Outputs JSON and exits.
	 */
	public function handle(): void {
		NonceHelper::verify( 'ugcc_admin' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$item_id_input = filter_input( INPUT_POST, 'id', FILTER_VALIDATE_INT );
		$item_id       = is_int( $item_id_input ) ? $item_id_input : 0;

		if ( ! $item_id ) {
			wp_send_json_error( array( 'message' => 'Invalid item ID.' ) );
		}

		$success = ItemRepository::instance()->delete( $item_id );
		$success ? wp_send_json_success() : wp_send_json_error( array( 'message' => 'Item not found.' ) );
	}
}
