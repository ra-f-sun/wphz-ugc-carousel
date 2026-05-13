<?php
/**
 * Admin configuration tab template.
 *
 * @package WPHZ\UGCCarousels
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are injected by TemplateLoader::render(), which calls include() inside a static method. PHP scopes the included file to that function's stack frame, so these variables never enter global scope. Plugin Check flags them as a false positive due to the static-analysis limitation.
?>
<form id="ugcc-config-form" data-action="ugcc_save_config">
	<?php wp_nonce_field( 'ugcc_admin', 'ugcc_nonce' ); ?>
	<input type="hidden" name="carousel_id" value="<?php echo esc_attr( $id ); ?>">

	<table class="form-table" role="presentation">
		<tr>
			<th>
				<label for="ugcc_name"><?php esc_html_e( 'Internal Name', 'ugc-carousels-for-woo' ); ?></label>
			</th>
			<td>
				<input type="text"
						id="ugcc_name"
						name="name"
						value="<?php echo esc_attr( $name ); ?>"
						class="regular-text" required>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Default Sound', 'ugc-carousels-for-woo' ); ?></th>
			<td>
				<label>
					<input type="radio" name="mute" value="1" <?php checked( $mute, true ); ?>>
					<?php esc_html_e( 'Mute', 'ugc-carousels-for-woo' ); ?>
				</label>
				<label style="margin-left:16px">
					<input type="radio" name="mute" value="0" <?php checked( $mute, false ); ?>>
					<?php esc_html_e( 'Unmute', 'ugc-carousels-for-woo' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Default Slide Direction', 'ugc-carousels-for-woo' ); ?></th>
			<td>
				<label>
					<input type="radio" name="direction" value="ltr" <?php checked( $direction, 'ltr' ); ?>>
					<?php esc_html_e( 'Left to Right', 'ugc-carousels-for-woo' ); ?>
				</label>
				<label style="margin-left:16px">
					<input type="radio" name="direction" value="rtl" <?php checked( $direction, 'rtl' ); ?>>
					<?php esc_html_e( 'Right to Left', 'ugc-carousels-for-woo' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Product Carousel', 'ugc-carousels-for-woo' ); ?></th>
			<td>
				<label>
					<input type="radio" name="show_products" value="1" <?php checked( (int) ( $show_products ?? 1 ), 1 ); ?>>
					<?php esc_html_e( 'Show', 'ugc-carousels-for-woo' ); ?>
				</label>
				<label style="margin-left:16px">
					<input type="radio" name="show_products" value="0" <?php checked( (int) ( $show_products ?? 1 ), 0 ); ?>>
					<?php esc_html_e( 'Hide', 'ugc-carousels-for-woo' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'When hidden, no product cards are shown on any slide regardless of attached products.', 'ugc-carousels-for-woo' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Default Add to Cart', 'ugc-carousels-for-woo' ); ?></th>
			<td>
				<label>
					<input type="radio" name="hide_atc" value="0" <?php checked( (int) ( $hide_atc ?? 0 ), 0 ); ?>>
					<?php esc_html_e( 'Show Add to Cart', 'ugc-carousels-for-woo' ); ?>
				</label>
				<label style="margin-left:16px">
					<input type="radio" name="hide_atc" value="1" <?php checked( (int) ( $hide_atc ?? 0 ), 1 ); ?>>
					<?php esc_html_e( 'Hide Add to Cart', 'ugc-carousels-for-woo' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Per-product settings on each video row can override this default.', 'ugc-carousels-for-woo' ); ?></p>
			</td>
		</tr>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary">
			<?php esc_html_e( 'Save Configuration', 'ugc-carousels-for-woo' ); ?>
		</button>
		<span class="ugcc-save-status"></span>
	</p>
</form>
