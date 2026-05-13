<?php
/**
 * Ajax handler for saving carousel configuration.
 *
 * @package WPHZ\UGCCarousels
 */

namespace WPHZ\UGCCarousels\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGCCarousels\AbstractSingleton;
use WPHZ\UGCCarousels\Helpers\NonceHelper;
use WPHZ\UGCCarousels\Helpers\SanitizeHelper;
use WPHZ\UGCCarousels\Repository\CarouselRepository;

/**
 * SaveConfig.
 */
class SaveConfig extends AbstractSingleton {


	/**
	 * Register WordPress hooks for this component.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_ugcc_save_config', array( $this, 'handle' ) );
	}

	/**
	 * Handle save-config AJAX request — persists carousel display settings.
	 *
	 * Expects POST fields:
	 *  - nonce       string  WordPress nonce for 'ugcc_admin'.
	 *  - carousel_id int     Carousel to update.
	 *  - name        string  Carousel display name.
	 *  - mute        string  '1' to mute videos by default, anything else for unmuted.
	 *  - direction   string  Scroll direction: 'ltr' or 'rtl'.
	 *  - hide_atc      int     Global add-to-cart visibility override (0 = show, 1 = hide).
	 *  - show_products int     Product carousel visibility (1 = show, 0 = hide entirely).
	 *
	 * @since  1.0.0
	 * @return void  Outputs JSON and exits.
	 */
	public function handle(): void {
		NonceHelper::verify( 'ugcc_admin' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$name_input          = filter_input( INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$mute_input          = filter_input( INPUT_POST, 'mute', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$direction_input     = filter_input( INPUT_POST, 'direction', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$hide_atc_input      = filter_input( INPUT_POST, 'hide_atc', FILTER_VALIDATE_INT );
		$show_products_input = filter_input( INPUT_POST, 'show_products', FILTER_VALIDATE_INT );
		$carousel_id_in      = filter_input( INPUT_POST, 'carousel_id', FILTER_VALIDATE_INT );

		$name          = is_string( $name_input ) ? $name_input : 'New Carousel';
		$mute          = is_string( $mute_input ) && '1' === $mute_input;
		$direction     = is_string( $direction_input ) ? $direction_input : '';
		$hide_atc      = is_int( $hide_atc_input ) ? $hide_atc_input : 1;
		$show_products = is_int( $show_products_input ) ? $show_products_input : 1;
		$carousel_id   = is_int( $carousel_id_in ) ? $carousel_id_in : 0;

		$carousel_config = array(
			'name'          => SanitizeHelper::text( $name ),
			'mute'          => $mute,
			'direction'     => in_array( $direction, array( 'ltr', 'rtl' ), true )
				? $direction
				: 'ltr',
			'hide_atc'      => $hide_atc,
			'show_products' => $show_products,
		);

		if ( $carousel_id > 0 ) {
			CarouselRepository::instance()->update( $carousel_id, $carousel_config );
			wp_send_json_success( array( 'message' => __( 'Configuration saved.', 'ugc-carousels-for-woo' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Invalid Carousel ID.', 'ugc-carousels-for-woo' ) ) );
		}
	}
}
