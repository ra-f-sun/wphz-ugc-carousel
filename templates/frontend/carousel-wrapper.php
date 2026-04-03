<?php
defined('ABSPATH') || exit;
/**
 * @var string $id
 * @var array  $items
 * @var string $sound
 * @var string $slide
 * @var bool   $is_muted
 * @var int    $global_hide_atc
 */
?>
<div class="wphz-ugc-carousel"
         data-carousel-id="<?php echo esc_attr($id); ?>"
         data-muted="<?php echo $is_muted ? '1' : '0'; ?>"
         data-direction="<?php echo esc_attr($slide); ?>">

    <div class="wphz-ugc-stage">
        <div class="wphz-ugc-track">
            <?php foreach ($items as $index => $item): ?>
                <?php \WPHZ\UGC\Helpers\TemplateLoader::render('frontend/carousel-item', [
                    'item'            => $item,
                    'index'           => $index,
                    'is_muted'        => $is_muted,
                    'active'          => $index === 0, // First item is active by default
                    'global_hide_atc' => $global_hide_atc,
                ]); ?>
            <?php endforeach; ?>
        </div>
    </div>

</div>
