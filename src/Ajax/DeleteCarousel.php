<?php
/**
 * Ajax handler for deleting a carousel.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Repository\CarouselRepository;
use WPHZ\UGC\Repository\ItemRepository;

/**
 * DeleteCarousel.
 */
class DeleteCarousel extends AbstractSingleton {


	/**
	 * Register WordPress hooks for this component.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_wphz_ugc_delete_carousel', array( $this, 'handle' ) );
	}

	/**
	 * Handle delete-carousel action including cascading item deletion.
	 *
	 * Expects GET fields:
	 *  - wphz_nonce  string  WordPress nonce for 'wphz_ugc_admin'.
	 *  - id          int     Carousel ID to delete.
	 *
	 * @since  1.0.0
	 * @return void  Redirects to the carousel list and exits.
	 */
	public function handle(): void {
		$nonce = filter_input( INPUT_GET, 'wphz_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ?? '';
		if ( ! wp_verify_nonce( $nonce, 'wphz_ugc_admin' ) ) {
			wp_die( 'Nonce verification failed.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$carousel_id = (int) filter_input( INPUT_GET, 'id', FILTER_VALIDATE_INT );

		if ( $carousel_id > 0 ) {
			CarouselRepository::instance()->delete( $carousel_id );
			ItemRepository::instance()->delete_by_carousel( $carousel_id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wphz-ugc-carousel&wphz_msg=deleted' ) );
		exit;
	}
}
