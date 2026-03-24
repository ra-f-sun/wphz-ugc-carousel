<?php
defined('ABSPATH') || exit;
/**
 * @var array $item
 * @var int   $index
 * @var bool  $is_muted
 * @var bool  $active
 */

$products     = $item['products'] ?? [];
$has_products = count($products) > 0;
$multi_prod   = count($products) > 1;
$active_class = $active ? 'wphz-ugc-slide--active' : '';
?>
<div class="wphz-ugc-slide <?php echo esc_attr($active_class); ?>" data-index="<?php echo (int) $index; ?>">

    <!-- Video Area -->
    <div class="wphz-ugc-video-wrap">
        <video class="wphz-ugc-video"
               data-src-hd="<?php echo esc_url($item['video_url_hd'] ?? ''); ?>"
               data-src-sd="<?php echo esc_url($item['video_url_sd'] ?? ''); ?>"
               playsinline
               <?php if ($is_muted) echo 'muted'; ?>
               preload="<?php echo $active ? 'auto' : 'metadata'; ?>"></video>

        <button type="button" class="wphz-ugc-mute-btn" aria-label="<?php esc_attr_e('Toggle sound', 'wphz-ugc'); ?>">
            <!-- Muted icon -->
            <span class="wphz-icon-mute" style="display: <?php echo $is_muted ? 'inline-block' : 'none'; ?>;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line></svg>
            </span>
            <!-- Unmuted icon -->
            <span class="wphz-icon-unmute" style="display: <?php echo $is_muted ? 'none' : 'inline-block'; ?>;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path><path d="M19.07 4.93a10 10 0 0 1 0 14.14"></path></svg>
            </span>
        </button>
    </div>

    <!-- Product Sub-Carousel Area -->
    <?php if ($has_products): ?>
        <div class="wphz-ugc-products <?php echo $multi_prod ? 'wphz-ugc-products--carousel' : ''; ?>">
            <div class="wphz-ugc-products-track">
                <?php foreach ($products as $p_data): ?>
                    <?php \WPHZ\UGC\Helpers\TemplateLoader::render('frontend/product-item', [
                        'product'  => $p_data['model'],
                        'hide_atc' => $p_data['hide_atc'],
                    ]); ?>
                <?php endforeach; ?>
            </div>

            <?php if ($multi_prod): ?>
                <button type="button" class="wphz-product-arrow wphz-product-arrow--prev" aria-label="<?php esc_attr_e('Previous product', 'wphz-ugc'); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                </button>
                <button type="button" class="wphz-product-arrow wphz-product-arrow--next" aria-label="<?php esc_attr_e('Next product', 'wphz-ugc'); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>
