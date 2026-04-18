<?php
/**
 * Admin content tab template.
 *
 * @package WPHZ\UGCCarousels
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are injected by TemplateLoader::render(), which calls include() inside a static method. PHP scopes the included file to that function's stack frame, so these variables never enter global scope. Plugin Check flags them as a false positive due to the static-analysis limitation.
?>
<div class="ugcc-content-manager">

	<!-- Shortcode display -->
	<div class="ugcc-shortcode-box">
		<label><?php esc_html_e( 'Your Shortcode:', 'ugc-carousels-for-woo' ); ?></label>
		<code id="ugcc-shortcode"><?php echo esc_html( $shortcode ); ?></code>
		<button type="button" class="button" id="ugcc-copy-shortcode">
			<?php esc_html_e( 'Copy', 'ugc-carousels-for-woo' ); ?>
		</button>
	</div>

	<!-- External Controls Instruction Snippet -->
	<div class="ugcc-integration-box">
		<h3><?php esc_html_e( 'Connecting External Buttons (Elementor, etc.)', 'ugc-carousels-for-woo' ); ?></h3>
		<p>
			<?php esc_html_e( 'To trigger slides from your own custom buttons, simply add the following HTML attributes to any element on your page.', 'ugc-carousels-for-woo' ); ?>
		</p>
		<p style="margin-bottom: 5px;">
			<strong><?php esc_html_e( 'Next Button Custom Attributes:', 'ugc-carousels-for-woo' ); ?></strong>
		</p>
		<code>data-ugcc-target="<?php echo esc_html( $id ); ?>" data-ugcc-action="next"</code>

		<p style="margin-top: 15px; margin-bottom: 5px;">
			<strong><?php esc_html_e( 'Previous Button Custom Attributes:', 'ugc-carousels-for-woo' ); ?></strong>
		</p>
		<code>data-ugcc-target="<?php echo esc_html( $id ); ?>" data-ugcc-action="prev"</code>

		<p style="margin-top: 15px; color: #646970;">
			<em><?php esc_html_e( 'No custom Javascript or HTML snippets required! The plugin automatically binds to these elements via active event delegation.', 'ugc-carousels-for-woo' ); ?></em>
		</p>
	</div>

	<!-- Sortable line items -->
	<ul id="ugcc-items-list" class="ugcc-items-list" style="margin-top:12px;">
		<?php foreach ( $items as $item ) : ?>
			<?php
			\WPHZ\UGCCarousels\Helpers\TemplateLoader::render(
				'admin/item-row',
				array(
					'item' => $item,
				)
			);
			?>
		<?php endforeach; ?>
	</ul>

	<!-- Add Blank Row Control -->
	<div style="display: flex; align-items: center; margin: 20px 0;">
		<hr style="flex: 1; border: 0; border-top: 1px dashed #ccc; margin: 0;">
		<button type="button" class="button button-secondary" id="ugcc-add-blank-row" style="margin: 0 15px; border-radius: 20px; padding: 0 20px; box-shadow: none;">
			+ <?php esc_html_e( 'Add New Video Row', 'ugc-carousels-for-woo' ); ?>
		</button>
		<hr style="flex: 1; border: 0; border-top: 1px dashed #ccc; margin: 0;">
	</div>

	<!-- Save -->
	<p class="submit" style="margin-top: 20px;">
		<input type="hidden" id="ugcc-carousel-id" value="<?php echo esc_attr( $id ); ?>">
		<button type="button" class="button button-primary" id="ugcc-save-content">
			<?php esc_html_e( 'Save Videos', 'ugc-carousels-for-woo' ); ?>
		</button>
		<span class="ugcc-save-status"></span>
	</p>

	<!-- CSV Export / Import -->
	<div class="ugcc-csv-actions">
		<a href="
		<?php
		echo esc_url(
			add_query_arg(
				array(
					'action'      => 'ugcc_export_csv',
					'carousel_id' => $id,
					'nonce'       => wp_create_nonce( 'ugcc_admin' ),
				),
				admin_url( 'admin-ajax.php' )
			)
		);
		?>
		"
			class="button button-secondary">
			<?php esc_html_e( 'Export CSV', 'ugc-carousels-for-woo' ); ?>
		</a>
		<label class="button button-secondary" for="ugcc-import-file" style="cursor:pointer; margin:0;">
			<?php esc_html_e( 'Import CSV', 'ugc-carousels-for-woo' ); ?>
		</label>
		<input type="file" id="ugcc-import-file" accept=".csv" style="display:none;">
		<span id="ugcc-import-status"></span>
	</div>

	<!-- JavaScript HTML Templates -->
	<script type="text/html" id="tmpl-ugcc-item-row">
		<li class="ugcc-item-row" data-id="{{rowId}}">
			<div class="ugcc-item-header">
				<span class="dashicons dashicons-move ugcc-drag-handle"></span>
				<button type="button" class="button button-link-delete ugcc-delete-item" data-id="{{rowId}}"><?php esc_html_e( 'Remove', 'ugc-carousels-for-woo' ); ?></button>
			</div>
			<div class="ugcc-item-section ugcc-item-section--video">
				<div class="ugcc-video-sources">
					<div class="ugcc-source-group ugcc-source-group--hd">
						<label><?php esc_html_e( 'HD Video (1080p, Broadband)', 'ugc-carousels-for-woo' ); ?></label>
						<div class="ugcc-source-row">
							<input type="url" name="items[{{rowId}}][video_url_hd]" value="" placeholder="https:// ..." class="ugcc-url-input">.
							<button type="button" class="button ugcc-media-btn" data-media-type="video"><?php esc_html_e( 'Media Library', 'ugc-carousels-for-woo' ); ?></button>
						</div>
						<input type="hidden" name="items[{{rowId}}][video_id]" value="0">
					</div>
					<div class="ugcc-source-group ugcc-source-group--sd">
						<label><?php esc_html_e( 'SD Video (480p, Mobile Fallback)', 'ugc-carousels-for-woo' ); ?></label>
						<div class="ugcc-source-row">
							<input type="url" name="items[{{rowId}}][video_url_sd]" value="" placeholder="https:// ..." class="ugcc-url-input">.
							<button type="button" class="button ugcc-media-btn" data-media-type="video"><?php esc_html_e( 'Media Library', 'ugc-carousels-for-woo' ); ?></button>
						</div>
					</div>
					<div class="ugcc-source-group ugcc-source-group--poster">
						<label><?php esc_html_e( 'Poster Image', 'ugc-carousels-for-woo' ); ?></label>
						<div class="ugcc-source-row">
							<input type="url" name="items[{{rowId}}][poster_url]" value="" placeholder="https:// ..." class="ugcc-url-input">.
							<button type="button" class="button ugcc-media-btn" data-media-type="image"><?php esc_html_e( 'Media Library', 'ugc-carousels-for-woo' ); ?></button>
						</div>
					</div>
				</div>
			</div>
			<div class="ugcc-item-section ugcc-item-section--products">
				<div class="ugcc-item-products">
					<label class="ugcc-section-label"><?php esc_html_e( 'Attached Products', 'ugc-carousels-for-woo' ); ?></label>
					<div class="ugcc-product-search-wrap">
						<input type="text" class="ugcc-product-search" placeholder="<?php esc_attr_e( 'Search by name, SKU or ID&hellip;', 'ugc-carousels-for-woo' ); ?>" data-row="{{rowId}}" autocomplete="off">
						<ul class="ugcc-product-suggestions" style="display:none;"></ul>
					</div>
					<ul class="ugcc-selected-products"></ul>
				</div>
			</div>
		</li>
	</script>

	<script type="text/html" id="tmpl-ugcc-product-chip">
		<li class="ugcc-chip" data-id="{{productId}}">
			<div class="ugcc-chip-header">
				<span class="ugcc-chip-name">{{productName}}</span>
				<button type="button" class="ugcc-remove-product" title="Remove">&times;</button>
			</div>
			<label class="ugcc-chip-option">
				<select name="items[{{rowId}}][products][{{productId}}][hide_atc]" class="ugcc-atc-override">
					<option value=""><?php esc_html_e( 'Global default', 'ugc-carousels-for-woo' ); ?></option>
					<option value="0"><?php esc_html_e( 'Force show ATC', 'ugc-carousels-for-woo' ); ?></option>
					<option value="1"><?php esc_html_e( 'Force hide ATC', 'ugc-carousels-for-woo' ); ?></option>
				</select>
			</label>
			<input type="hidden" name="items[{{rowId}}][products][{{productId}}][id]" value="{{productId}}">
		</li>
	</script>

	<script type="text/html" id="tmpl-ugcc-product-suggestion">
		<li data-id="{{productId}}" data-name="{{productNameRaw}}">
			{{thumbHtml}}
			<span>{{productNameTxt}}</span>
			<small class="ugcc-product-sku">{{skuHtml}}</small>
			<small>{{productPrice}}</small>
			{{statusBadge}}
			{{visibilityBadge}}
		</li>
	</script>

</div>
