<?php
/**
 * CarouselRepository.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGC\AbstractSingleton;

/**
 * CarouselRepository.
 */
class CarouselRepository extends AbstractSingleton {

	/**
	 * Table.
	 *
	 * @return string Return value.
	 */
	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wphz_ugc_carousels';
	}

	/**
	 * Get_all.
	 *
	 * @return array<int, array> Return value.
	 */
	public function get_all(): array {
		global $wpdb;
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix; table names cannot use prepare() placeholders.
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );
		return ! empty( $rows ) ? $rows : array();
	}

	/**
	 * Get_by_id.
	 *
	 * @param int $id Parameter value.
	 * @return ?array Return value.
	 */
	public function get_by_id( int $id ): ?array {
		global $wpdb;
		$table  = $this->table();
		$result = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix; table names cannot use prepare() placeholders.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);
		return $result ? $result : null;
	}

	/**
	 * Insert.
	 *
	 * @param array $data Parameter value.
	 * @return int|false Return value.
	 */
	public function insert( array $data ): int|false {
		global $wpdb;

		$insert_data = array(
			'name'           => sanitize_text_field( $data['name'] ?? 'New Carousel' ),
			'heading'        => sanitize_text_field( $data['heading'] ?? '' ),
			'subheading'     => sanitize_text_field( $data['subheading'] ?? '' ),
			'mute'           => isset( $data['mute'] ) ? (int) $data['mute'] : 1,
			'direction'      => sanitize_text_field( $data['direction'] ?? 'ltr' ),
			'hide_atc'       => isset( $data['hide_atc'] ) ? (int) $data['hide_atc'] : 0,
			'on_arrow_right' => sanitize_text_field( $data['on_arrow_right'] ?? '' ),
			'on_arrow_left'  => sanitize_text_field( $data['on_arrow_left'] ?? '' ),
		);

		// custom_css uses array_key_exists so saving '' correctly clears the value.
		if ( array_key_exists( 'custom_css', $data ) ) {
			$insert_data['custom_css'] = wp_strip_all_tags( $data['custom_css'] );
		}

		$result = $wpdb->insert( $this->table(), $insert_data );
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update.
	 *
	 * @param int   $id   Parameter value.
	 * @param array $data Parameter value.
	 * @return bool Return value.
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$fields = array();

		if ( isset( $data['name'] ) ) {
			$fields['name'] = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['heading'] ) ) {
			$fields['heading'] = sanitize_text_field( $data['heading'] );
		}
		if ( isset( $data['subheading'] ) ) {
			$fields['subheading'] = sanitize_text_field( $data['subheading'] );
		}
		if ( isset( $data['mute'] ) ) {
			$fields['mute'] = (int) $data['mute'];
		}
		if ( isset( $data['direction'] ) ) {
			$fields['direction'] = sanitize_text_field( $data['direction'] );
		}
		// array_key_exists: hide_atc value of 0 is falsy, so isset() would skip it.
		if ( array_key_exists( 'hide_atc', $data ) ) {
			$fields['hide_atc'] = (int) $data['hide_atc'];
		}
		if ( isset( $data['on_arrow_right'] ) ) {
			$fields['on_arrow_right'] = sanitize_text_field( $data['on_arrow_right'] );
		}
		if ( isset( $data['on_arrow_left'] ) ) {
			$fields['on_arrow_left'] = sanitize_text_field( $data['on_arrow_left'] );
		}

		// array_key_exists: isset() would skip an intentional '' (clear CSS).
		if ( array_key_exists( 'custom_css', $data ) ) {
			$fields['custom_css'] = wp_strip_all_tags( $data['custom_css'] );
		}

		if ( empty( $fields ) ) {
			return false;
		}

		// $wpdb->update returns int|false. 0 means "matched but nothing changed".
		// which is still a success â€" hence !== false rather than (bool).
		return $wpdb->update( $this->table(), $fields, array( 'id' => $id ) ) !== false;
	}

	/**
	 * Delete.
	 *
	 * @param int $id Parameter value.
	 * @return bool Return value.
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( $this->table(), array( 'id' => $id ) );
	}
}
