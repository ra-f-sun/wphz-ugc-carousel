<?php
defined('ABSPATH') || exit;
$video_id     = (int) ($item['video_id'] ?? 0);
$video_url_hd = $item['video_url_hd'] ?? '';
$video_url_sd = $item['video_url_sd'] ?? '';
$product_ids  = $item['product_ids'] ?? [];   
$row_id       = $item['id'] ?? 'new-' . uniqid();
?>
<li class="wphz-item-row" data-id="<?php echo esc_attr($row_id); ?>">

    <span class="dashicons dashicons-move wphz-drag-handle"></span>

    <!-- Dual Video Sources -->
    <div class="wphz-video-sources">
        
        <!-- HD Source -->
        <div class="wphz-source-group wphz-source-group--hd">
            <label><?php esc_html_e('HD Video (1080p, Broadband)', 'wphz-ugc'); ?></label>
            <div class="wphz-source-row">
                <input type="url" 
                       name="items[<?php echo esc_attr($row_id); ?>][video_url_hd]" 
                       value="<?php echo esc_url($video_url_hd); ?>" 
                       placeholder="https://..." 
                       class="wphz-url-input regular-text">
                <button type="button" class="button wphz-media-btn"><?php esc_html_e('Media Library', 'wphz-ugc'); ?></button>
            </div>
            <input type="hidden" name="items[<?php echo esc_attr($row_id); ?>][video_id]" value="<?php echo esc_attr($video_id); ?>">
        </div>

        <!-- SD Source -->
        <div class="wphz-source-group wphz-source-group--sd">
            <label><?php esc_html_e('SD Video (480p, Mobile Fallback)', 'wphz-ugc'); ?></label>
            <div class="wphz-source-row">
                <input type="url" 
                       name="items[<?php echo esc_attr($row_id); ?>][video_url_sd]" 
                       value="<?php echo esc_url($video_url_sd); ?>" 
                       placeholder="https://..." 
                       class="wphz-url-input regular-text">
                <button type="button" class="button wphz-media-btn"><?php esc_html_e('Media Library', 'wphz-ugc'); ?></button>
            </div>
        </div>

    </div>

    <!-- Product search & chips -->
    <div class="wphz-item-products">
        <label><?php esc_html_e('Attached Products', 'wphz-ugc'); ?></label>

        <div class="wphz-product-search-wrap">
            <input type="text"
                   class="wphz-product-search"
                   placeholder="<?php esc_attr_e('Search products…', 'wphz-ugc'); ?>"
                   data-row="<?php echo esc_attr($row_id); ?>"
                   autocomplete="off">
            <ul class="wphz-product-suggestions" style="display:none;"></ul>
        </div>

        <ul class="wphz-selected-products">
            <?php foreach ($product_ids as $p_data): ?>
                <?php
                $p_id     = is_array($p_data) ? (int) ($p_data['id'] ?? 0) : (int) $p_data;
                $hide_atc = is_array($p_data) && !empty($p_data['hide_atc']);
                $product  = wc_get_product($p_id);
                if (!$product) continue;
                ?>
                <li class="wphz-chip" data-id="<?php echo esc_attr($p_id); ?>">
                    <div class="wphz-chip-header">
                        <span class="wphz-chip-name"><?php echo esc_html($product->get_name()); ?></span>
                        <button type="button" class="wphz-remove-product" title="Remove">×</button>
                    </div>
                    <label class="wphz-chip-option">
                        <input type="checkbox" name="items[<?php echo esc_attr($row_id); ?>][products][<?php echo esc_attr($p_id); ?>][hide_atc]" value="1" <?php checked($hide_atc, true); ?>>
                        <?php esc_html_e('Hide Add to Cart', 'wphz-ugc'); ?>
                    </label>
                    <input type="hidden" name="items[<?php echo esc_attr($row_id); ?>][products][<?php echo esc_attr($p_id); ?>][id]" value="<?php echo esc_attr($p_id); ?>">
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Delete row -->
    <button type="button"
            class="button wphz-delete-item"
            data-id="<?php echo esc_attr($row_id); ?>">
        <?php esc_html_e('Remove', 'wphz-ugc'); ?>
    </button>

</li>
