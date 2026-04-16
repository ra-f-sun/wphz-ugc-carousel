<?php
/**
 * Ajax handler for deleting a carousel.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Ajax;

defined( 'ABSPATH' ) || exit;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Repository\CarouselRepository;

/**
 * DeleteCarousel.
 */
class DeleteCarousel extends AbstractSingleton {


	/**
	 * Initialize hooks.
	 *
	 * @return void Return value.
	 */
	public function init(): void {
		add_action( 'wp_ajax_wphz_ugc_delete_carousel', array( $this, 'handle' ) );
	}

	/**
	 * Handle delete carousel action.
	 *
	 * @return void Return value.
	 */
	public function handle(): void {
		if ( ! isset( $_GET['wphz_nonce'] ) || ! wp_verify_nonce( $_GET['wphz_nonce'], 'wphz_ugc_admin' ) ) {
			wp_die( 'Nonce verification failed.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$carousel_id = (int) ( $_GET['id'] ?? 0 );

		if ( $carousel_id > 0 ) {
			CarouselRepository::instance()->delete( $carousel_id );

			// Cascade delete items.
			global $wpdb;
			$table = $wpdb->prefix . 'wphz_ugc_items';
			$wpdb->delete( $table, array( 'carousel_id' => (string) $carousel_id ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wphz-ugc-carousel&wphz_msg=deleted' ) );
		exit;
	}
}
