<?php
/**
 * CarouselRepository.
 *
 * @package WPHZ\UGCCarousels
 */

namespace WPHZ\UGCCarousels\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This class IS the database abstraction layer for the plugin-owned ugcc_carousels table. WordPress provides no object model (WP_Query, WP_Post) for plugin-defined schemas, so direct $wpdb calls are intentional and correct here. NoCaching is suppressed because caching belongs at the caller layer. UnescapedDBParameter is suppressed because $table is always $wpdb->prefix . 'ugcc_carousels' — a server-controlled constant never derived from user input; table names cannot use $wpdb->prepare() placeholders. All user-supplied values are sanitised and parameterised via $wpdb->prepare() or the typed wrappers.

use WPHZ\UGCCarousels\AbstractSingleton;

/**
 * CarouselRepository.
 */
class CarouselRepository extends AbstractSingleton {

	/**
	 * Return the fully qualified carousels table name.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'ugcc_carousels';
	}

	/**
	 * Retrieve all carousels ordered by most recently created.
	 *
	 * @since  1.0.0
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all(): array {
		global $wpdb;
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix; table names cannot use prepare() placeholders.
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );
		return ! empty( $rows ) ? $rows : array();
	}

	/**
	 * Retrieve a single carousel by its primary key.
	 *
	 * @since  1.0.0
	 * @param  int $id Carousel primary key.
	 * @return array<string, mixed>|null  Row data, or null if not found.
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
	 * Insert a new carousel row and return the new ID.
	 *
	 * @since  1.0.0
	 * @param  array<string, mixed> $data Column values. Unknown keys are ignored.
	 * @return int|false  New carousel ID on success, false on failure.
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
	 * Update one or more fields of an existing carousel.
	 *
	 * Returns true even when the matched row had no actual changes (0 affected rows
	 * from $wpdb->update is still a success).
	 *
	 * @since  1.0.0
	 * @param  int                  $id   Carousel primary key.
	 * @param  array<string, mixed> $data Fields to update. Unknown keys are ignored.
	 * @return bool  True on success or no-op, false on DB error or empty $data.
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
		// which is still a success — hence !== false rather than (bool).
		return $wpdb->update( $this->table(), $fields, array( 'id' => $id ) ) !== false;
	}

	/**
	 * Delete a carousel by its primary key.
	 *
	 * @since  1.0.0
	 * @param  int $id Carousel primary key.
	 * @return bool  True if a row was deleted, false otherwise.
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( $this->table(), array( 'id' => $id ) );
	}
}
