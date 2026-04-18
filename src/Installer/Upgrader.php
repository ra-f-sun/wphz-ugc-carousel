<?php
/**
 * Database migration for existing installs upgrading from the old table/option names.
 *
 * @package WPHZ\UGCCarousels
 */

namespace WPHZ\UGCCarousels\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SHOW TABLES and RENAME TABLE are DDL statements with no WordPress API equivalent; caching is meaningless for one-time upgrade queries. UnescapedDBParameter is suppressed because all table name variables are $wpdb->prefix . 'ugcc_*' or 'wphz_ugc_*' — server-controlled constants, never user-supplied; table names cannot use $wpdb->prepare() placeholders. SHOW TABLES arguments ARE prepared via $wpdb->prepare(). These queries run only on activation/upgrade by a privileged admin.

/**
 * Upgrader.
 */
class Upgrader {

	/**
	 * Rename old tables and option keys when upgrading from the pre-2.0 naming scheme.
	 *
	 * Safe to call on every activation and plugins_loaded; it short-circuits when
	 * the old tables no longer exist.
	 *
	 * @since  2.0.0
	 * @return void
	 */
	public static function maybe_migrate(): void {
		global $wpdb;

		$old_carousels = $wpdb->prefix . 'wphz_ugc_carousels';
		$new_carousels = $wpdb->prefix . 'ugcc_carousels';
		$old_items     = $wpdb->prefix . 'wphz_ugc_items';
		$new_items     = $wpdb->prefix . 'ugcc_items';

		$old_carousels_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_carousels ) ) === $old_carousels;
		$new_carousels_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_carousels ) ) === $new_carousels;

		if ( $old_carousels_exists && ! $new_carousels_exists ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are derived from $wpdb->prefix; cannot use placeholders.
			$wpdb->query( "RENAME TABLE {$old_carousels} TO {$new_carousels}" );
		}

		$old_items_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_items ) ) === $old_items;
		$new_items_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_items ) ) === $new_items;

		if ( $old_items_exists && ! $new_items_exists ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are derived from $wpdb->prefix; cannot use placeholders.
			$wpdb->query( "RENAME TABLE {$old_items} TO {$new_items}" );
		}

		// Migrate option key from old name to new name.
		$old_version = get_option( 'wphz_ugc_db_version' );
		if ( false !== $old_version && false === get_option( 'ugcc_db_version' ) ) {
			update_option( 'ugcc_db_version', $old_version );
			delete_option( 'wphz_ugc_db_version' );
		}
	}
}
