<?php
/**
 * Ajax handler for saving custom CSS.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Ajax;

defined( 'ABSPATH' ) || exit;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Repository\CarouselRepository;

/**
 * SaveCustomCss.
 */
class SaveCustomCss extends AbstractSingleton {

	/**
	 * Initialize hooks.
	 *
	 * @return void Return value.
	 */
	public function init(): void {
		add_action( 'wp_ajax_wphz_ugc_save_custom_css', array( $this, 'handle' ) );
	}

	/**
	 * Handle save custom CSS action.
	 *
	 * @return void Return value.
	 */
	public function handle(): void {
		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! is_string( $nonce ) || '' === $nonce ) {
			$nonce = filter_input( INPUT_POST, 'wphz_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		}

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wphz_ugc_admin' ) ) {
			wp_send_json_error( array( 'message' => 'Nonce verification failed.' ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$carousel_id_input = filter_input( INPUT_POST, 'carousel_id', FILTER_VALIDATE_INT );
		$carousel_id       = is_int( $carousel_id_input ) ? $carousel_id_input : 0;
		if ( $carousel_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid Carousel ID.', 'wphz-ugc' ) ) );
			return; // Explicit return; do not rely on wp_die() inside wp_send_json_error().
		}

		// wp_strip_all_tags: removes </style> injection and all HTML tags.
		// wp_unslash: reverses WordPress magic-quotes applied to POST values.
		$custom_css_raw = filter_input( INPUT_POST, 'custom_css', FILTER_UNSAFE_RAW );
		$custom_css     = is_string( $custom_css_raw ) ? $custom_css_raw : '';
		$css            = wp_strip_all_tags( wp_unslash( $custom_css ) );

		$updated = CarouselRepository::instance()->update(
			$carousel_id,
			array(
				'custom_css' => $css,
			)
		);

		if ( $updated ) {
			wp_send_json_success( array( 'message' => __( 'Custom CSS saved.', 'wphz-ugc' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to save.', 'wphz-ugc' ) ) );
		}
	}
}
