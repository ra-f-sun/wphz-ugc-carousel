<?php
/**
 * Frontend product item template.
 *
 * @package WPHZ\UGCCarousels
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are injected by TemplateLoader::render(), which calls include() inside a static method. PHP scopes the included file to that function's stack frame, so these variables never enter global scope. Plugin Check flags them as a false positive due to the static-analysis limitation.
/**
 * Product item context.
 *
 * @var \WC_Product $product
 * @var mixed       $hide_atc        null = inherit global, 0 = force show, 1 = force hide
 * @var int         $global_hide_atc 0 = show (default), 1 = hide
 */

// Resolve effective ATC visibility: per-product overrides global default.
	$effective_hide_atc = ( null === $hide_atc || false === $hide_atc )
	? (int) ( $global_hide_atc ?? 0 )
	: (int) $hide_atc;

$product_url = $product->get_permalink();
$has_link    = ! empty( $product_url );
?>
<div class="ugcc-product-item">

	<?php if ( $has_link ) : ?>
		<a class="ugcc-product-img-link" href="<?php echo esc_url( $product_url ); ?>" aria-label="<?php echo esc_attr( $product->get_name() ); ?>">
			<div class="ugcc-product-img">
				<?php echo wp_kses_post( $product->get_image( 'thumbnail' ) ); ?>
			</div>
		</a>
	<?php else : ?>
		<div class="ugcc-product-img">
			<?php echo wp_kses_post( $product->get_image( 'thumbnail' ) ); ?>
		</div>
	<?php endif; ?>

	<div class="ugcc-product-info">
		<h4 class="ugcc-product-name">
			<?php if ( $has_link ) : ?>
				<a class="ugcc-product-name-link" href="<?php echo esc_url( $product_url ); ?>">
					<?php echo esc_html( $product->get_name() ); ?>
				</a>
			<?php else : ?>
				<?php echo esc_html( $product->get_name() ); ?>
			<?php endif; ?>
		</h4>
		<div class="ugcc-product-price">
			<?php echo wp_kses_post( \WPHZ\UGCCarousels\Helpers\PriceHelper::get_clean_price( $product ) ); ?>
		</div>

		<?php if ( ! $effective_hide_atc ) : ?>
			<button type="button"
				class="ugcc-atc-btn"
				data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
				<?php esc_html_e( 'Add to Cart', 'ugc-carousels-for-woo' ); ?>
			</button>
			<?php
		endif;
		?>
	</div>

</div>
