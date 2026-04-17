<?php
/**
 * Admin configuration tab template.
 *
 * @package WPHZ\UGCCarousels
 */

defined( 'ABSPATH' ) || exit; ?>
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
