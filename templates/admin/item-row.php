<?php
/**
 * Admin item row template.
 *
 * @package WPHZ\UGCCarousels
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are injected by TemplateLoader::render(), which calls include() inside a static method. PHP scopes the included file to that function's stack frame, so these variables never enter global scope. Plugin Check flags them as a false positive due to the static-analysis limitation.
$video_id     = (int) ( $item['video_id'] ?? 0 );
$video_url_hd = $item['video_url_hd'] ?? '';
$video_url_sd = $item['video_url_sd'] ?? '';
$poster_url   = $item['poster_url'] ?? '';
$product_ids  = $item['product_ids'] ?? array();
$row_id       = $item['id'] ?? 'new-' . uniqid();
?>
<li class="ugcc-item-row" data-id="<?php echo esc_attr( $row_id ); ?>">

	<!-- Row header: drag handle + remove -->
	<div class="ugcc-item-header">
		<span class="dashicons dashicons-move ugcc-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'ugc-carousels-for-woo' ); ?>"></span>
		<button type="button"
			class="button button-link-delete ugcc-delete-item"
			data-id="<?php echo esc_attr( $row_id ); ?>">
			<?php esc_html_e( 'Remove', 'ugc-carousels-for-woo' ); ?>
		</button>
	</div>

	<!-- Section 1: Video & Poster sources -->
	<div class="ugcc-item-section ugcc-item-section--video">
		<div class="ugcc-video-sources">

			<!-- HD Source -->
			<div class="ugcc-source-group ugcc-source-group--hd">
				<label><?php esc_html_e( 'HD Video (1080p, Broadband)', 'ugc-carousels-for-woo' ); ?></label>
				<div class="ugcc-source-row">
					<input type="url"
						name="items[<?php echo esc_attr( $row_id ); ?>][video_url_hd]"
						value="<?php echo esc_url( $video_url_hd ); ?>"
						placeholder="https:// ..."
						class="ugcc-url-input">
					<button type="button" class="button ugcc-media-btn" data-media-type="video"><?php esc_html_e( 'Media Library', 'ugc-carousels-for-woo' ); ?></button>
				</div>
				<input type="hidden" name="items[<?php echo esc_attr( $row_id ); ?>][video_id]" value="<?php echo esc_attr( $video_id ); ?>">
			</div>

			<!-- SD Source -->
			<div class="ugcc-source-group ugcc-source-group--sd">
				<label><?php esc_html_e( 'SD Video (480p, Mobile Fallback)', 'ugc-carousels-for-woo' ); ?></label>
				<div class="ugcc-source-row">
					<input type="url"
						name="items[<?php echo esc_attr( $row_id ); ?>][video_url_sd]"
						value="<?php echo esc_url( $video_url_sd ); ?>"
						placeholder="https:// ..."
						class="ugcc-url-input">
					<button type="button" class="button ugcc-media-btn" data-media-type="video"><?php esc_html_e( 'Media Library', 'ugc-carousels-for-woo' ); ?></button>
				</div>
			</div>

			<!-- Poster -->
			<div class="ugcc-source-group ugcc-source-group--poster">
				<label><?php esc_html_e( 'Poster Image', 'ugc-carousels-for-woo' ); ?></label>
				<div class="ugcc-source-row">
					<input type="url"
						name="items[<?php echo esc_attr( $row_id ); ?>][poster_url]"
						value="<?php echo esc_url( $poster_url ); ?>"
						placeholder="https:// ..."
						class="ugcc-url-input">
					<button type="button" class="button ugcc-media-btn" data-media-type="image"><?php esc_html_e( 'Media Library', 'ugc-carousels-for-woo' ); ?></button>
				</div>
			</div>

		</div>
	</div>

	<!-- Section 2: Attached Products -->
	<div class="ugcc-item-section ugcc-item-section--products">
		<div class="ugcc-item-products">
			<label class="ugcc-section-label"><?php esc_html_e( 'Attached Products', 'ugc-carousels-for-woo' ); ?></label>

			<div class="ugcc-product-search-wrap">
				<input type="text"
					class="ugcc-product-search"
					placeholder="<?php esc_attr_e( 'Search by name, SKU or ID&hellip;', 'ugc-carousels-for-woo' ); ?>"
					data-row="<?php echo esc_attr( $row_id ); ?>"
					autocomplete="off">
				<ul class="ugcc-product-suggestions" style="display:none;"></ul>
			</div>

			<ul class="ugcc-selected-products">
				<?php foreach ( $product_ids as $p_data ) : ?>
					<?php
					$p_id    = is_array( $p_data ) ? (int) ( $p_data['id'] ?? 0 ) : (int) $p_data;
					$product = wc_get_product( $p_id );
					if ( ! $product ) {
						continue;
					}
					// Only '1' (force hide) is a meaningful explicit override.
					// null and 0 both mean "use global default" — 0 was the old
					// implicit default before the 3-state system existed.
					$hide_atc_val = ( is_array( $p_data ) && ! empty( $p_data['hide_atc'] ) )
						? '1'
						: '';
					?>
					<li class="ugcc-chip" data-id="<?php echo esc_attr( $p_id ); ?>">
						<div class="ugcc-chip-header">
							<span class="ugcc-chip-name"><?php echo esc_html( $product->get_name() ); ?></span>
							<button type="button" class="ugcc-remove-product" title="<?php esc_attr_e( 'Remove', 'ugc-carousels-for-woo' ); ?>">&times;</button>
						</div>
						<label class="ugcc-chip-option">
							<select name="items[<?php echo esc_attr( $row_id ); ?>][products][<?php echo esc_attr( $p_id ); ?>][hide_atc]" class="ugcc-atc-override">
								<option value="" <?php selected( $hide_atc_val, '' ); ?>><?php esc_html_e( 'Global default', 'ugc-carousels-for-woo' ); ?></option>
								<option value="0" <?php selected( $hide_atc_val, '0' ); ?>><?php esc_html_e( 'Force show ATC', 'ugc-carousels-for-woo' ); ?></option>
								<option value="1" <?php selected( $hide_atc_val, '1' ); ?>><?php esc_html_e( 'Force hide ATC', 'ugc-carousels-for-woo' ); ?></option>
							</select>
						</label>
						<input type="hidden" name="items[<?php echo esc_attr( $row_id ); ?>][products][<?php echo esc_attr( $p_id ); ?>][id]" value="<?php echo esc_attr( $p_id ); ?>">
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>

</li>
