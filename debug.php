<?php
/**
 * Debug helper script.
 *
 * @package WPHZ\\UGC
 */

$dir        = __DIR__;
$dir_length = strlen( $dir );
while ( ! file_exists( $dir . '/wp-load.php' ) && $dir_length > 5 ) {
	$dir        = dirname( $dir );
	$dir_length = strlen( $dir );
}
if ( file_exists( $dir . '/wp-load.php' ) ) {
	require_once $dir . '/wp-load.php';
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT id, video_id, product_ids FROM wp_wphz_ugc_items ORDER BY id DESC LIMIT 5', ARRAY_A );
	echo '<pre>' . esc_html( wp_json_encode( $rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '</pre>';
} else {
	echo 'No wp-load found';
}
