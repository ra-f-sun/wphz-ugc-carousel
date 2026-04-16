<?php
/**
 * Ajax handler for creating a carousel.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Ajax;

defined( 'ABSPATH' ) || exit;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\NonceHelper;
use WPHZ\UGC\Helpers\SanitizeHelper;
use WPHZ\UGC\Repository\CarouselRepository;

/**
 * CreateCarousel.
 */
class CreateCarousel extends AbstractSingleton {


	/**
	 * Initialize hooks.
	 *
	 * @return void Return value.
	 */
	public function init(): void {
		add_action( 'admin_action_wphz_ugc_create_carousel', array( $this, 'handle' ) );
	}

	/**
	 * Handle create carousel action.
	 *
	 * @return void Return value.
	 */
	public function handle(): void {
		NonceHelper::verify( 'wphz_ugc_admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$name_input = filter_input( INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! is_string( $name_input ) || '' === $name_input ) {
			$name_input = 'New Carousel';
		}

		$carousel_name = SanitizeHelper::text( $name_input );

		$carousel_id = CarouselRepository::instance()->insert( array( 'name' => $carousel_name ) );

		if ( $carousel_id ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wphz-ugc-carousel&action=edit&id=' . $carousel_id . '&tab=config' ) );
			exit;
		}

		wp_die( 'Failed to create carousel.' );
	}
}
