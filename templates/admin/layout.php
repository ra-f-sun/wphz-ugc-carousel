<?php
/**
 * Admin layout template.
 *
 * @package WPHZ\UGCCarousels
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are injected by TemplateLoader::render(), which calls include() inside a static method. PHP scopes the included file to that function's stack frame, so these variables never enter global scope. Plugin Check flags them as a false positive due to the static-analysis limitation.
?>
<div class="wrap ugcc-admin">
	<a href="?page=ugc-carousels-for-woo" class="page-title-action" style="margin-bottom: 10px; display: inline-block;">&larr; Back to All Carousels</a>
	<h1><?php esc_html_e( 'Edit UGC Carousel', 'ugc-carousels-for-woo' ); ?></h1>

	<nav class="nav-tab-wrapper">
		<a href="?page=ugc-carousels-for-woo&action=edit&id=<?php echo esc_attr( $id ); ?>&tab=config"
			class="nav-tab <?php echo 'config' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Configuration', 'ugc-carousels-for-woo' ); ?>
		</a>
		<a href="?page=ugc-carousels-for-woo&action=edit&id=<?php echo esc_attr( $id ); ?>&tab=content"
			class="nav-tab <?php echo 'content' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Content', 'ugc-carousels-for-woo' ); ?>
		</a>
		<a href="?page=ugc-carousels-for-woo&action=edit&id=<?php echo esc_attr( $id ); ?>&tab=custom-css"
			class="nav-tab <?php echo 'custom-css' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Custom CSS', 'ugc-carousels-for-woo' ); ?>
		</a>
	</nav>

	<div class="tab-content">
		<?php if ( 'config' === $active_tab ) : ?>
			<?php
			\WPHZ\UGCCarousels\Helpers\TemplateLoader::render(
				'admin/config-tab',
				\WPHZ\UGCCarousels\Admin\ConfigPage::instance()->get_data( $id )
			);
			?>
		<?php elseif ( 'content' === $active_tab ) : ?>
			<?php
			\WPHZ\UGCCarousels\Helpers\TemplateLoader::render(
				'admin/content-tab',
				\WPHZ\UGCCarousels\Admin\ContentPage::instance()->get_data( $id )
			);
			?>
		<?php elseif ( 'custom-css' === $active_tab ) : ?>
			<?php
			\WPHZ\UGCCarousels\Helpers\TemplateLoader::render(
				'admin/custom-css-tab',
				\WPHZ\UGCCarousels\Admin\ConfigPage::instance()->get_data( $id )
			);
			?>
		<?php endif; ?>
	</div>
</div>
