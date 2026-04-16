<?php
/**
 * Ajax handler for deleting an item.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Ajax;

defined( 'ABSPATH' ) || exit;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\NonceHelper;
use WPHZ\UGC\Repository\ItemRepository;

/**
 * DeleteItem.
 */
class DeleteItem extends AbstractSingleton {


	/**
	 * Initialize hooks.
	 *
	 * @return void Return value.
	 */
	public function init(): void {
		add_action( 'wp_ajax_wphz_ugc_delete_item', array( $this, 'handle' ) );
	}

	/**
	 * Handle delete item action.
	 *
	 * @return void Return value.
	 */
	public function handle(): void {
		NonceHelper::verify( 'wphz_ugc_admin' );
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
