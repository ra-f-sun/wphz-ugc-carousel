<?php
/**
 * ItemRepository.
 *
 * @package WPHZ\UGC
 */

namespace WPHZ\UGC\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPHZ\UGC\AbstractSingleton;

/**
 * ItemRepository.
 */
class ItemRepository extends AbstractSingleton {

	/**
	 * Table.
	 *
	 * @return string Return value.
	 */
	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wphz_ugc_items';
	}

	/**
	 * Get_all.
	 *
	 * @param string $carousel_id Parameter value.
	 * @return array<int, array> Return value.
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
	 * Update.
	 *
	 * @param int   $id   Parameter value.
	 * @param array $data Parameter value.
	 * @return bool Return value.
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
	 * Delete.
	 *
	 * @param int $id Parameter value.
	 * @return bool Return value.
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( $this->table(), array( 'id' => $id ) );
	}

	/**
	 * Bulk-update sort_order.
	 *
	 * @param array<int, int> $order [item_id => new_sort_order, ...].
	 */
	public function reorder( array $order ): void {
		foreach ( $order as $id => $sort ) {
			$this->update( (int) $id, array( 'sort_order' => (int) $sort ) );
		}
	}
}
