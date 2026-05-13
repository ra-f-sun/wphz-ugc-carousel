<?php
/**
 * Frontend carousel wrapper template.
 *
 * @package WPHZ\UGCCarousels
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are injected by TemplateLoader::render(), which calls include() inside a static method. PHP scopes the included file to that function's stack frame, so these variables never enter global scope. Plugin Check flags them as a false positive due to the static-analysis limitation.
/**
 * Carousel wrapper context.
 *
 * @var string $id
 * @var array  $items
 * @var string $sound
 * @var string $slide
 * @var bool   $is_muted
 * @var int    $global_hide_atc
 * @var bool   $show_products
 */
?>
<div class="ugcc-carousel"
		data-carousel-id="<?php echo esc_attr( $id ); ?>"
		data-muted="<?php echo $is_muted ? '1' : '0'; ?>"
		data-direction="<?php echo esc_attr( $slide ); ?>">

	<div class="ugcc-stage">
		<div class="ugcc-track">
			<?php foreach ( $items as $index => $item ) : ?>
				<?php
				\WPHZ\UGCCarousels\Helpers\TemplateLoader::render(
					'frontend/carousel-item',
					array(
						'item'            => $item,
						'index'           => $index,
						'is_muted'        => $is_muted,
						'active'          => 0 === $index, // First item is active by default.
						'global_hide_atc' => $global_hide_atc,
						'show_products'   => $show_products,
					)
				);
				?>
			<?php endforeach; ?>
		</div>
	</div>

</div>
