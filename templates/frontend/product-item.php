<?php
defined('ABSPATH') || exit;
/**
 * @var \WC_Product $product
 * @var mixed       $hide_atc        null = inherit global, 0 = force show, 1 = force hide
 * @var int         $global_hide_atc 0 = show (default), 1 = hide
 */

// Resolve effective ATC visibility: per-product overrides global default.
$effective_hide_atc = ($hide_atc === null || $hide_atc === false)
    ? (int) ($global_hide_atc ?? 0)
    : (int) $hide_atc;

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

        <?php if (!$effective_hide_atc): ?>
            <button type="button"
                class="wphz-ugc-atc-btn"
                data-product-id="<?php echo esc_attr($product->get_id()); ?>">
                <?php esc_html_e('Add to Cart', 'wphz-ugc'); ?>
            </button>
        <?php endif;
        ?>
    </div>

</div>