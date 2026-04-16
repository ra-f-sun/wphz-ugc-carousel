<?php
/**
 * item-row.
 *
 * @package WPHZ\\UGC
 */

defined( 'ABSPATH' ) || exit;
$video_id     = (int) ( $item['video_id'] ?? 0 );
$video_url_hd = $item['video_url_hd'] ?? '';
$video_url_sd = $item['video_url_sd'] ?? '';
$poster_url   = $item['poster_url'] ?? '';
$product_ids  = $item['product_ids'] ?? array();
$row_id       = $item['id'] ?? 'new-' . uniqid();
?>
<li class="wphz-item-row" data-id="<?php echo esc_attr( $row_id ); ?>">

	<!-- Row header: drag handle + remove -->
	<div class="wphz-item-header">
		<span class="dashicons dashicons-move wphz-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wphz-ugc' ); ?>"></span>
		<button type="button"
			class="button button-link-delete wphz-delete-item"
			data-id="<?php echo esc_attr( $row_id ); ?>">
			<?php esc_html_e( 'Remove', 'wphz-ugc' ); ?>
		</button>
	</div>

	<!-- Section 1: Video & Poster sources -->
	<div class="wphz-item-section wphz-item-section--video">
		<div class="wphz-video-sources">

			<!-- HD Source -->
			<div class="wphz-source-group wphz-source-group--hd">
				<label><?php esc_html_e( 'HD Video (1080p, Broadband)', 'wphz-ugc' ); ?></label>
				<div class="wphz-source-row">
					<input type="url"
						name="items[<?php echo esc_attr( $row_id ); ?>][video_url_hd]"
						value="<?php echo esc_url( $video_url_hd ); ?>"
						placeholder="https:// ...".
						class="wphz-url-input">
					<button type="button" class="button wphz-media-btn" data-media-type="video"><?php esc_html_e( 'Media Library', 'wphz-ugc' ); ?></button>
				</div>
				<input type="hidden" name="items[<?php echo esc_attr( $row_id ); ?>][video_id]" value="<?php echo esc_attr( $video_id ); ?>">
			</div>

			<!-- SD Source -->
			<div class="wphz-source-group wphz-source-group--sd">
				<label><?php esc_html_e( 'SD Video (480p, Mobile Fallback)', 'wphz-ugc' ); ?></label>
				<div class="wphz-source-row">
					<input type="url"
						name="items[<?php echo esc_attr( $row_id ); ?>][video_url_sd]"
						value="<?php echo esc_url( $video_url_sd ); ?>"
						placeholder="https:// ...".
						class="wphz-url-input">
					<button type="button" class="button wphz-media-btn" data-media-type="video"><?php esc_html_e( 'Media Library', 'wphz-ugc' ); ?></button>
				</div>
			</div>

			<!-- Poster -->
			<div class="wphz-source-group wphz-source-group--poster">
				<label><?php esc_html_e( 'Poster Image', 'wphz-ugc' ); ?></label>
				<div class="wphz-source-row">
					<input type="url"
						name="items[<?php echo esc_attr( $row_id ); ?>][poster_url]"
						value="<?php echo esc_url( $poster_url ); ?>"
						placeholder="https:// ...".
						class="wphz-url-input">
					<button type="button" class="button wphz-media-btn" data-media-type="image"><?php esc_html_e( 'Media Library', 'wphz-ugc' ); ?></button>
				</div>
			</div>

		</div>
	</div>

	<!-- Section 2: Attached Products -->
	<div class="wphz-item-section wphz-item-section--products">
		<div class="wphz-item-products">
			<label class="wphz-section-label"><?php esc_html_e( 'Attached Products', 'wphz-ugc' ); ?></label>

			<div class="wphz-product-search-wrap">
				<input type="text"
					class="wphz-product-search"
					placeholder="<?php esc_attr_e( 'Search by name, SKU or IDâ€¦', 'wphz-ugc' ); ?>"
					data-row="<?php echo esc_attr( $row_id ); ?>"
					autocomplete="off">
				<ul class="wphz-product-suggestions" style="display:none;"></ul>
			</div>

			<ul class="wphz-selected-products">
				<?php foreach ( $product_ids as $p_data ) : ?>
					<?php
					$p_id    = is_array( $p_data ) ? (int) ( $p_data['id'] ?? 0 ) : (int) $p_data;
					$product = wc_get_product( $p_id );
					if ( ! $product ) {
						continue;
					}
					// Only '1' (force hide) is a meaningful explicit override.
					// null and 0 both mean "use global default" â€” 0 was the old.
					// implicit default before the 3-state system existed.
					$hide_atc_val = ( is_array( $p_data ) && ! empty( $p_data['hide_atc'] ) )
						? '1'
						: '';
					?>
					<li class="wphz-chip" data-id="<?php echo esc_attr( $p_id ); ?>">
						<div class="wphz-chip-header">
							<span class="wphz-chip-name"><?php echo esc_html( $product->get_name() ); ?></span>
							<button type="button" class="wphz-remove-product" title="<?php esc_attr_e( 'Remove', 'wphz-ugc' ); ?>">Ã—</button>
						</div>
						<label class="wphz-chip-option">
							<select name="items[<?php echo esc_attr( $row_id ); ?>][products][<?php echo esc_attr( $p_id ); ?>][hide_atc]" class="wphz-atc-override">
								<option value="" <?php selected( $hide_atc_val, '' ); ?>><?php esc_html_e( 'Global default', 'wphz-ugc' ); ?></option>
								<option value="0" <?php selected( $hide_atc_val, '0' ); ?>><?php esc_html_e( 'Force show ATC', 'wphz-ugc' ); ?></option>
								<option value="1" <?php selected( $hide_atc_val, '1' ); ?>><?php esc_html_e( 'Force hide ATC', 'wphz-ugc' ); ?></option>
							</select>
						</label>
						<input type="hidden" name="items[<?php echo esc_attr( $row_id ); ?>][products][<?php echo esc_attr( $p_id ); ?>][id]" value="<?php echo esc_attr( $p_id ); ?>">
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>

</li>
