<?php
/**
 * debug.
 *
 * @package WPHZ\\UGC
 */

$dir = __DIR__;
while ( ! file_exists( $dir . '/wp-load.php' ) && strlen( $dir ) > 5 ) {
	$dir = dirname( $dir );
}
if ( file_exists( $dir . '/wp-load.php' ) ) {
	require_once $dir . '/wp-load.php';
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT id, video_id, product_ids FROM wp_wphz_ugc_items ORDER BY id DESC LIMIT 5', ARRAY_A );
	print_r( $rows );
} else {
	echo 'No wp-load found';
}
