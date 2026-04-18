<?php
/**
 * ItemRepository.
 *
 * @package WPHZ\UGCCarousels
 */

namespace WPHZ\UGCCarousels\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- This class IS the database abstraction layer for the plugin-owned ugcc_items table. WordPress provides no object model (WP_Query, WP_Post) for plugin-defined schemas, so direct $wpdb calls are intentional and correct here. NoCaching is suppressed because caching at this layer would require invalidation logic duplicated across all callers; any caching is the caller's responsibility. All user-supplied values are sanitised before reaching this layer and parameterised via $wpdb->prepare() or the typed $wpdb->insert/update/delete wrappers.

use WPHZ\UGCCarousels\AbstractSingleton;

/**
 * ItemRepository.
 */
class ItemRepository extends AbstractSingleton {

	/**
	 * Return the fully qualified items table name.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'ugcc_items';
	}

	/**
	 * Retrieve all items for a carousel, ordered by sort_order ascending.
	 *
	 * @since  1.0.0
	 * @param  string $carousel_id Parent carousel ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all( string $carousel_id = '1' ): array {
		global $wpdb;
		$table = $this->table();
		$rows  = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix; table names cannot use prepare() placeholders.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE carousel_id = %s ORDER BY sort_order ASC", $carousel_id ),
			ARRAY_A
		);
		return ! empty( $rows ) ? $rows : array();
	}

	/**
	 * Retrieve a single item by its primary key.
	 *
	 * @since  1.0.0
	 * @param  int $id Item primary key.
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
	 * Insert a new carousel item and return the new ID.
	 *
	 * @since  1.0.0
	 * @param  array<string, mixed> $data Column values. Unknown keys are ignored.
	 * @return int|false  New item ID on success, false on failure.
	 */
	public function insert( array $data ): int|false {
		global $wpdb;
		$result = $wpdb->insert(
			$this->table(),
			array(
				'carousel_id'  => $data['carousel_id'] ?? '1',
				'sort_order'   => (int) ( $data['sort_order'] ?? 0 ),
				'video_id'     => (int) ( $data['video_id'] ?? 0 ),
				'video_url_hd' => esc_url_raw( $data['video_url_hd'] ?? '' ),
				'video_url_sd' => esc_url_raw( $data['video_url_sd'] ?? '' ),
				'poster_url'   => esc_url_raw( $data['poster_url'] ?? '' ),
				'product_ids'  => wp_json_encode( $data['product_ids'] ?? array() ),
			)
		);
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update one or more fields of an existing item.
	 *
	 * @since  1.0.0
	 * @param  int                  $id   Item primary key.
	 * @param  array<string, mixed> $data Fields to update. Unknown keys are ignored.
	 * @return bool  True on success, false on DB error or empty $data.
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$fields = array();
		if ( isset( $data['sort_order'] ) ) {
			$fields['sort_order'] = (int) $data['sort_order'];
		}
		if ( isset( $data['product_ids'] ) ) {
			$fields['product_ids'] = wp_json_encode( $data['product_ids'] );
		}
		if ( isset( $data['video_url_hd'] ) ) {
			$fields['video_url_hd'] = esc_url_raw( $data['video_url_hd'] );
		}
		if ( isset( $data['video_url_sd'] ) ) {
			$fields['video_url_sd'] = esc_url_raw( $data['video_url_sd'] );
		}
		if ( isset( $data['poster_url'] ) ) {
			$fields['poster_url'] = esc_url_raw( $data['poster_url'] );
		}
		if ( empty( $fields ) ) {
			return false;
		}
		return (bool) $wpdb->update( $this->table(), $fields, array( 'id' => $id ) );
	}

	/**
	 * Delete a single item by its primary key.
	 *
	 * @since  1.0.0
	 * @param  int $id Item primary key.
	 * @return bool  True if a row was deleted, false otherwise.
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( $this->table(), array( 'id' => $id ) );
	}

	/**
	 * Delete all items belonging to a carousel.
	 *
	 * @since  1.0.0
	 * @param  int $carousel_id The parent carousel ID.
	 * @return void
	 */
	public function delete_by_carousel( int $carousel_id ): void {
		global $wpdb;
		$wpdb->delete(
			$this->table(),
			array( 'carousel_id' => (string) $carousel_id ),
			array( '%s' )
		);
	}

	/**
	 * Bulk-update the sort_order of multiple items in one call.
	 *
	 * @since  1.0.0
	 * @param  array<int, int> $order Map of item_id → new_sort_order.
	 * @return void
	 */
	public function reorder( array $order ): void {
		foreach ( $order as $id => $sort ) {
			$this->update( (int) $id, array( 'sort_order' => (int) $sort ) );
		}
	}
}
