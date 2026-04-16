<?php
/**
 * Config page data provider.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Admin;

defined( 'ABSPATH' ) || exit;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Repository\CarouselRepository;

/**
 * ConfigPage.
 */
class ConfigPage extends AbstractSingleton {

	/**
	 * Provides data to the config-tab template. No output here.
	 *
	 * @param int $id Carousel ID.
	 * @return array<string, mixed>
	 */
	public function get_data( int $id ): array {
		$config = CarouselRepository::instance()->get_by_id( $id );
		if ( ! is_array( $config ) ) {
			$config = array();
		}

		return array(
			'id'             => $id,
			'name'           => $config['name'] ?? '',
			'heading'        => $config['heading'] ?? '',
			'subheading'     => $config['subheading'] ?? '',
			'mute'           => (bool) ( $config['mute'] ?? true ),
			'direction'      => $config['direction'] ?? 'ltr',
			'hide_atc'       => (int) ( $config['hide_atc'] ?? 0 ),
			'on_arrow_right' => $config['on_arrow_right'] ?? '',
			'on_arrow_left'  => $config['on_arrow_left'] ?? '',
			'custom_css'     => $config['custom_css'] ?? '',
		);
	}
}
