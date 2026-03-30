<?php
defined('ABSPATH') || exit;
/**
 * @var \WC_Product $product
 */

$product_url = $product->get_permalink();
$has_link    = !empty($product_url);
?>
<div class="wphz-ugc-product-item">

    <?php if ($has_link): ?>
        <a class="wphz-ugc-product-img-link" href="<?php echo esc_url($product_url); ?>" aria-label="<?php echo esc_attr($product->get_name()); ?>">
            <div class="wphz-ugc-product-img">
                <?php echo wp_kses_post($product->get_image('thumbnail')); ?>
            </div>
        </a>
    <?php else: ?>
        <div class="wphz-ugc-product-img">
            <?php echo wp_kses_post($product->get_image('thumbnail')); ?>
        </div>
    <?php endif; ?>

    <div class="wphz-ugc-product-info">
        <h4 class="wphz-ugc-product-name">
            <?php if ($has_link): ?>
                <a class="wphz-ugc-product-name-link" href="<?php echo esc_url($product_url); ?>">
                    <?php echo esc_html($product->get_name()); ?>
                </a>
            <?php else: ?>
                <?php echo esc_html($product->get_name()); ?>
            <?php endif; ?>
        </h4>
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