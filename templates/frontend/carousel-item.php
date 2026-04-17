<?php
/**
 * Frontend carousel item template.
 *
 * @package WPHZ\UGCCarousels
 */

defined( 'ABSPATH' ) || exit;
/**
 * Carousel item context.
 *
 * @var array $item
 * @var int   $index
 * @var bool  $is_muted
 * @var bool  $active
 * @var int   $global_hide_atc
 */

$products     = $item['products'] ?? array();
$has_products = count( $products ) > 0;
$multi_prod   = count( $products ) > 1;
$active_class = $active ? 'ugcc-slide--active' : '';
$poster_url   = $item['poster_url'] ?? '';

// Dynamically generate a targeting class based on the video filename (e.g., ugc1.mp4 -> ugcc-video-ugc1).
$primary_video_url = ! empty( $item['video_url_hd'] ) ? $item['video_url_hd'] : ( ! empty( $item['video_url_sd'] ) ? $item['video_url_sd'] : '' );
$video_class       = 'ugcc-video';
if ( $primary_video_url ) {
	$video_url      = wp_parse_url( $primary_video_url );
	$video_path     = is_array( $video_url ) ? ( $video_url['path'] ?? '' ) : '';
	$video_filename = pathinfo( (string) $video_path, PATHINFO_FILENAME );
	if ( $video_filename ) {
		$video_class .= ' ugcc-video-' . sanitize_html_class( $video_filename );
	}
}
?>
<div class="ugcc-slide <?php echo esc_attr( $active_class ); ?>" data-index="<?php echo (int) $index; ?>">

	<!-- Video Area -->
	<div class="ugcc-video-wrap">
		<video class="<?php echo esc_attr( $video_class ); ?>"
			data-src-hd="<?php echo esc_url( $item['video_url_hd'] ?? '' ); ?>"
			data-src-sd="<?php echo esc_url( $item['video_url_sd'] ?? '' ); ?>"
			data-poster-url="<?php echo esc_url( $poster_url ); ?>"
			poster="<?php echo esc_url( $poster_url ); ?>"
			crossorigin="anonymous"
			playsinline
			<?php
			if ( $is_muted ) {
				echo 'muted';}
			?>
			preload="none"></video>

		<img class="ugcc-poster-img"
			src="<?php echo esc_url( $poster_url ); ?>"
			alt=""
			aria-hidden="true"
			draggable="false">

		<button type="button" class="ugcc-mute-btn" aria-label="<?php esc_attr_e( 'Toggle sound', 'ugc-carousels-for-woo' ); ?>">
			<!-- Muted icon -->
			<span class="ugcc-icon-mute" style="display: <?php echo $is_muted ? 'flex' : 'none'; ?>;">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
					<line x1="23" y1="9" x2="17" y2="15"></line>
					<line x1="17" y1="9" x2="23" y2="15"></line>
				</svg>
			</span>
			<!-- Unmuted icon -->
			<span class="ugcc-icon-unmute" style="display: <?php echo $is_muted ? 'none' : 'flex'; ?>;">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
					<path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path>
					<path d="M19.07 4.93a10 10 0 0 1 0 14.14"></path>
				</svg>
			</span>
		</button>
	</div>

	<!-- Product Sub-Carousel Area -->
	<?php if ( $has_products ) : ?>
		<div class="ugcc-products <?php echo $multi_prod ? 'ugcc-products--carousel' : ''; ?>">
			<div class="ugcc-products-track">
				<?php foreach ( $products as $p_data ) : ?>
					<?php
					\WPHZ\UGCCarousels\Helpers\TemplateLoader::render(
						'frontend/product-item',
						array(
							'product'         => $p_data['model'],
							'hide_atc'        => $p_data['hide_atc'],
							'global_hide_atc' => $global_hide_atc,
						)
					);
					?>
				<?php endforeach; ?>
			</div>

			<?php if ( $multi_prod ) : ?>
				<button type="button" class="ugcc-product-arrow ugcc-product-arrow--prev" aria-label="<?php esc_attr_e( 'Previous product', 'ugc-carousels-for-woo' ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="m15 18-6-6 6-6" />
					</svg>
				</button>
				<button type="button" class="ugcc-product-arrow ugcc-product-arrow--next" aria-label="<?php esc_attr_e( 'Next product', 'ugc-carousels-for-woo' ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="m9 18 6-6-6-6" />
					</svg>
				</button>
			<?php endif; ?>
		</div>
	<?php endif; ?>

</div>
