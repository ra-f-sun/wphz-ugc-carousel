<?php
/**
 * Admin menu registration and rendering.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\TemplateLoader;
use WPHZ\UGC\Repository\CarouselRepository;

/**
 * Admin menu controller.
 */
class AdminMenu extends AbstractSingleton {


	/**
	 * Register WordPress hooks for this component.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register the top-level admin menu page for the plugin.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'UGC Carousel', 'wphz-ugc' ),
			__( 'UGC Carousel', 'wphz-ugc' ),
			'manage_options',
			'wphz-ugc-carousel',
			array( $this, 'render_page' ),
			'dashicons-video-alt3',
			58
		);
	}

	/**
	 * Render the admin page — dispatches to list view or edit view based on GET params.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render_page(): void {
		$action = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$action = is_string( $action ) ? sanitize_text_field( $action ) : 'list';

		$id = filter_input( INPUT_GET, 'id', FILTER_VALIDATE_INT );
		$id = is_int( $id ) ? $id : 0;

		if ( 'edit' === $action && $id > 0 ) {
			$tab        = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$active_tab = is_string( $tab ) ? sanitize_key( $tab ) : 'config';
			TemplateLoader::render(
				'admin/layout',
				array(
					'id'         => $id,
					'active_tab' => $active_tab,
				)
			);
		} else {
			$carousels = CarouselRepository::instance()->get_all();
			TemplateLoader::render(
				'admin/list-view',
				array(
					'carousels' => $carousels,
				)
			);
		}
	}
}
