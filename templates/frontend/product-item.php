<?php
defined('ABSPATH') || exit;
/**
 * @var \WC_Product $product
 */
?>
<div class="wphz-ugc-product-item">
    
    <div class="wphz-ugc-product-img">
        <?php echo wp_kses_post($product->get_image('thumbnail')); ?>
    </div>

    <div class="wphz-ugc-product-info">
        <h4 class="wphz-ugc-product-name"><?php echo esc_html($product->get_name()); ?></h4>
        <div class="wphz-ugc-product-price">
            <?php echo wp_kses_post(\WPHZ\UGC\Helpers\PriceHelper::get_clean_price($product)); ?>
        </div>
        
        <?php if (empty($hide_atc)): ?>
            <button type="button"
                    class="button wphz-ugc-atc-btn"
                    data-product-id="<?php echo esc_attr($product->get_id()); ?>">
                <?php esc_html_e('Add to Cart', 'wphz-ugc'); ?>
            </button>
        <?php endif; ?>
    </div>

</div>
