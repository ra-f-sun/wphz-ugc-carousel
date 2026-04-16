<?php
/**
 * Admin configuration tab template.
 *
 * @package WPHZ\UGC
 */

defined( 'ABSPATH' ) || exit; ?>
<?php defined( 'ABSPATH' ) || exit; ?>
<form id="wphz-ugc-config-form" data-action="wphz_ugc_save_config">
	<?php wp_nonce_field( 'wphz_ugc_admin', 'wphz_nonce' ); ?>
	<input type="hidden" name="carousel_id" value="<?php echo esc_attr( $id ); ?>">

	<table class="form-table" role="presentation">
		<tr>
			<th>
				<label for="wphz_name"><?php esc_html_e( 'Internal Name', 'wphz-ugc' ); ?></label>
			</th>
			<td>
				<input type="text"
						id="wphz_name"
						name="name"
						value="<?php echo esc_attr( $name ); ?>"
						class="regular-text" required>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Default Sound', 'wphz-ugc' ); ?></th>
			<td>
				<label>
					<input type="radio" name="mute" value="1" <?php checked( $mute, true ); ?>>
					<?php esc_html_e( 'Mute', 'wphz-ugc' ); ?>
				</label>
				<label style="margin-left:16px">
					<input type="radio" name="mute" value="0" <?php checked( $mute, false ); ?>>
					<?php esc_html_e( 'Unmute', 'wphz-ugc' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Default Slide Direction', 'wphz-ugc' ); ?></th>
			<td>
				<label>
					<input type="radio" name="direction" value="ltr" <?php checked( $direction, 'ltr' ); ?>>
					<?php esc_html_e( 'Left to Right', 'wphz-ugc' ); ?>
				</label>
				<label style="margin-left:16px">
					<input type="radio" name="direction" value="rtl" <?php checked( $direction, 'rtl' ); ?>>
					<?php esc_html_e( 'Right to Left', 'wphz-ugc' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Default Add to Cart', 'wphz-ugc' ); ?></th>
			<td>
				<label>
					<input type="radio" name="hide_atc" value="0" <?php checked( (int) ( $hide_atc ?? 0 ), 0 ); ?>>
					<?php esc_html_e( 'Show Add to Cart', 'wphz-ugc' ); ?>
				</label>
				<label style="margin-left:16px">
					<input type="radio" name="hide_atc" value="1" <?php checked( (int) ( $hide_atc ?? 0 ), 1 ); ?>>
					<?php esc_html_e( 'Hide Add to Cart', 'wphz-ugc' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Per-product settings on each video row can override this default.', 'wphz-ugc' ); ?></p>
			</td>
		</tr>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary">
			<?php esc_html_e( 'Save Configuration', 'wphz-ugc' ); ?>
		</button>
		<span class="wphz-save-status"></span>
	</p>
</form>
