<?php defined('ABSPATH') || exit; ?>
<div class="wphz-content-manager">

    <!-- Shortcode display -->
    <div class="wphz-shortcode-box">
        <label><?php esc_html_e('Your Shortcode:', 'wphz-ugc'); ?></label>
        <code id="wphz-shortcode"><?php echo esc_html($shortcode); ?></code>
        <button type="button" class="button" id="wphz-copy-shortcode">
            <?php esc_html_e('Copy', 'wphz-ugc'); ?>
        </button>
    </div>

    <!-- External Controls Instruction Snippet -->
    <div class="wphz-integration-box">
        <h3><?php esc_html_e('Connecting External Buttons (Elementor, etc.)', 'wphz-ugc'); ?></h3>
        <p>
            <?php esc_html_e('To trigger slides from your own custom buttons, simply add the following HTML attributes to any element on your page.', 'wphz-ugc'); ?> 
        </p>
        <p style="margin-bottom: 5px;">
            <strong><?php esc_html_e('Next Button Custom Attributes:', 'wphz-ugc'); ?></strong>
        </p>
        <code>data-wphz-target="<?php echo esc_html($id); ?>" data-wphz-action="next"</code>

        <p style="margin-top: 15px; margin-bottom: 5px;">
            <strong><?php esc_html_e('Previous Button Custom Attributes:', 'wphz-ugc'); ?></strong>
        </p>
        <code>data-wphz-target="<?php echo esc_html($id); ?>" data-wphz-action="prev"</code>
        
        <p style="margin-top: 15px; color: #646970;">
            <em><?php esc_html_e('No custom Javascript or HTML snippets required! The plugin automatically binds to these elements via active event delegation.', 'wphz-ugc'); ?></em>
        </p>
    </div>

    <!-- Sortable line items -->
    <ul id="wphz-items-list" class="wphz-items-list" style="margin-top:12px;">
        <?php foreach ($items as $item): ?>
            <?php \WPHZ\UGC\Helpers\TemplateLoader::render('admin/item-row', [
                'item' => $item,
            ]); ?>
        <?php endforeach; ?>
    </ul>

    <!-- Add Blank Row Control -->
    <div style="display: flex; align-items: center; margin: 20px 0;">
        <hr style="flex: 1; border: 0; border-top: 1px dashed #ccc; margin: 0;">
        <button type="button" class="button button-secondary" id="wphz-add-blank-row" style="margin: 0 15px; border-radius: 20px; padding: 0 20px; box-shadow: none;">
            + <?php esc_html_e('Add New Video Row', 'wphz-ugc'); ?>
        </button>
        <hr style="flex: 1; border: 0; border-top: 1px dashed #ccc; margin: 0;">
    </div>

    <!-- Save -->
    <p class="submit" style="margin-top: 20px;">
        <input type="hidden" id="wphz-carousel-id" value="<?php echo esc_attr($id); ?>">
        <button type="button" class="button button-primary" id="wphz-save-content">
            <?php esc_html_e('Save Videos', 'wphz-ugc'); ?>
        </button>
        <span class="wphz-save-status"></span>
    </p>

    <!-- CSV Export / Import -->
    <div class="wphz-csv-actions">
        <a href="<?php echo esc_url(add_query_arg([
            'action'      => 'wphz_ugc_export_csv',
            'carousel_id' => $id,
            'nonce'       => wp_create_nonce('wphz_ugc_admin'),
        ], admin_url('admin-ajax.php'))); ?>"
           class="button button-secondary">
            <?php esc_html_e('Export CSV', 'wphz-ugc'); ?>
        </a>
        <label class="button button-secondary" for="wphz-import-file" style="cursor:pointer; margin:0;">
            <?php esc_html_e('Import CSV', 'wphz-ugc'); ?>
        </label>
        <input type="file" id="wphz-import-file" accept=".csv" style="display:none;">
        <span id="wphz-import-status"></span>
    </div>

    <!-- JavaScript HTML Templates -->
    <script type="text/html" id="tmpl-wphz-item-row">
        <li class="wphz-item-row" data-id="{{rowId}}">
            <div class="wphz-item-header">
                <span class="dashicons dashicons-move wphz-drag-handle"></span>
                <button type="button" class="button button-link-delete wphz-delete-item" data-id="{{rowId}}"><?php esc_html_e('Remove', 'wphz-ugc'); ?></button>
            </div>
            <div class="wphz-item-section wphz-item-section--video">
                <div class="wphz-video-sources">
                    <div class="wphz-source-group wphz-source-group--hd">
                        <label><?php esc_html_e('HD Video (1080p, Broadband)', 'wphz-ugc'); ?></label>
                        <div class="wphz-source-row">
                            <input type="url" name="items[{{rowId}}][video_url_hd]" value="" placeholder="https://..." class="wphz-url-input">
                            <button type="button" class="button wphz-media-btn" data-media-type="video"><?php esc_html_e('Media Library', 'wphz-ugc'); ?></button>
                        </div>
                        <input type="hidden" name="items[{{rowId}}][video_id]" value="0">
                    </div>
                    <div class="wphz-source-group wphz-source-group--sd">
                        <label><?php esc_html_e('SD Video (480p, Mobile Fallback)', 'wphz-ugc'); ?></label>
                        <div class="wphz-source-row">
                            <input type="url" name="items[{{rowId}}][video_url_sd]" value="" placeholder="https://..." class="wphz-url-input">
                            <button type="button" class="button wphz-media-btn" data-media-type="video"><?php esc_html_e('Media Library', 'wphz-ugc'); ?></button>
                        </div>
                    </div>
                    <div class="wphz-source-group wphz-source-group--poster">
                        <label><?php esc_html_e('Poster Image', 'wphz-ugc'); ?></label>
                        <div class="wphz-source-row">
                            <input type="url" name="items[{{rowId}}][poster_url]" value="" placeholder="https://..." class="wphz-url-input">
                            <button type="button" class="button wphz-media-btn" data-media-type="image"><?php esc_html_e('Media Library', 'wphz-ugc'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="wphz-item-section wphz-item-section--products">
                <div class="wphz-item-products">
                    <label class="wphz-section-label"><?php esc_html_e('Attached Products', 'wphz-ugc'); ?></label>
                    <div class="wphz-product-search-wrap">
                        <input type="text" class="wphz-product-search" placeholder="<?php esc_attr_e('Search by name, SKU or ID…', 'wphz-ugc'); ?>" data-row="{{rowId}}" autocomplete="off">
                        <ul class="wphz-product-suggestions" style="display:none;"></ul>
                    </div>
                    <ul class="wphz-selected-products"></ul>
                </div>
            </div>
        </li>
    </script>

    <script type="text/html" id="tmpl-wphz-product-chip">
        <li class="wphz-chip" data-id="{{productId}}">
            <div class="wphz-chip-header">
                <span class="wphz-chip-name">{{productName}}</span>
                <button type="button" class="wphz-remove-product" title="Remove">×</button>
            </div>
            <label class="wphz-chip-option">
                <select name="items[{{rowId}}][products][{{productId}}][hide_atc]" class="wphz-atc-override">
                    <option value=""><?php esc_html_e('Global default', 'wphz-ugc'); ?></option>
                    <option value="0"><?php esc_html_e('Force show ATC', 'wphz-ugc'); ?></option>
                    <option value="1"><?php esc_html_e('Force hide ATC', 'wphz-ugc'); ?></option>
                </select>
            </label>
            <input type="hidden" name="items[{{rowId}}][products][{{productId}}][id]" value="{{productId}}">
        </li>
    </script>

    <script type="text/html" id="tmpl-wphz-product-suggestion">
        <li data-id="{{productId}}" data-name="{{productNameRaw}}">
            {{thumbHtml}}
            <span>{{productNameTxt}}</span>
            <small class="wphz-product-sku">{{skuHtml}}</small>
            <small>{{productPrice}}</small>
            {{statusBadge}}
            {{visibilityBadge}}
        </li>
    </script>

</div>
