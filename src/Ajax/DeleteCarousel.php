<?php
/**
 * Ajax handler for deleting a carousel.
 *
 * @package WPHZ\UGCCarousels
 */

namespace WPHZ\UGCCarousels\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGCCarousels\AbstractSingleton;
use WPHZ\UGCCarousels\Repository\CarouselRepository;
use WPHZ\UGCCarousels\Repository\ItemRepository;

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
		add_action( 'wp_ajax_ugcc_delete_carousel', array( $this, 'handle' ) );
	}

	/**
	 * Handle delete-carousel action including cascading item deletion.
	 *
	 * Expects GET fields:
	 *  - ugcc_nonce  string  WordPress nonce for 'ugcc_admin'.
	 *  - id          int     Carousel ID to delete.
	 *
	 * @since  1.0.0
	 * @return void  Redirects to the carousel list and exits.
	 */
	public function handle(): void {
		$nonce = filter_input( INPUT_GET, 'ugcc_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ?? '';
		if ( ! wp_verify_nonce( $nonce, 'ugcc_admin' ) ) {
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

		wp_safe_redirect( admin_url( 'admin.php?page=ugc-carousels-for-woo&ugcc_msg=deleted' ) );
		exit;
	}
}
