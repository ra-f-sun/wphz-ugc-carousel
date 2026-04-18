<?php
/**
 * Admin custom CSS tab template.
 *
 * @package WPHZ\UGCCarousels
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are injected by TemplateLoader::render(), which calls include() inside a static method. PHP scopes the included file to that function's stack frame, so these variables never enter global scope. Plugin Check flags them as a false positive due to the static-analysis limitation.
?>
<form id="ugcc-custom-css-form" data-action="ugcc_save_custom_css">
	<?php wp_nonce_field( 'ugcc_admin', 'ugcc_nonce' ); ?>
	<input type="hidden" name="carousel_id" value="<?php echo esc_attr( $id ); ?>">

	<p style="margin-top: 1.5rem; color: #555;">
		<?php
		esc_html_e(
			'Write CSS that applies only to this carousel. Use the selector shown in the hint below to target this carousel specifically.',
			'ugc-carousels-for-woo'
		);
		?>
	</p>

	<p>
		<code style="background:#f0f0f0; padding: 4px 8px; border-radius:3px; font-size:13px;">
			[data-carousel-id="<?php echo esc_attr( $id ); ?>"] .ugcc-video-wrap { }
		</code>
	</p>

	<div style="margin-top: 1rem;">
		<textarea
			id="ugcc_custom_css"
			name="custom_css"
			rows="20"
			style="width:100%; font-family:monospace; font-size:13px;"
		><?php echo esc_textarea( $custom_css ?? '' ); ?></textarea>
	</div>

	<p class="submit">
		<button type="submit" class="button button-primary" id="ugcc-save-custom-css">
			<?php esc_html_e( 'Save CSS', 'ugc-carousels-for-woo' ); ?>
		</button>
		<span class="ugcc-save-status"></span>
	</p>
</form>

<script>
// Activate WordPress CodeMirror on the textarea (CSS mode).
// wp_enqueue_code_editor() is called by the admin AssetLoader when this tab is active.
document.addEventListener('DOMContentLoaded', function () {
	if (typeof wp === 'undefined' || !wp.codeEditor) return;

	// Store the editor instance so we can manually sync it later.
	var editor = wp.codeEditor.initialize(document.getElementById('ugcc_custom_css'), {
		codemirror: {
			mode: 'css',
			lineNumbers: true,
			lineWrapping: true,
			indentUnit: 4,
		}
	});

	// Custom CSS Save Handler.
	var $form = jQuery('#ugcc-custom-css-form');
	$form.on('submit', function (e) {
		e.preventDefault();

		// CodeMirror intercepts the textarea. If we preventDefault(), it doesn't auto-sync!
		// We MUST force it to save its current buffer back into the textarea before reading .val().
		if (editor && editor.codemirror) {
			editor.codemirror.save();
		}

		var $btn = jQuery('#ugcc-save-custom-css');
		var $status = $form.find('.ugcc-save-status');

		$btn.prop('disabled', true);

		jQuery.post(ugccAdmin.ajaxurl, {
			action:      $form.data('action'),
			nonce:       ugccAdmin.nonce,
			carousel_id: $form.find('[name="carousel_id"]').val(),
			custom_css:  $form.find('[name="custom_css"]').val(),
		})
		.done(function (res) {
			$status.text((res.data && res.data.message) ? res.data.message : 'Saved!')
					.css('color', 'green');
		})
		.fail(function () {
			$status.text('Error saving.').css('color', 'red');
		})
		.always(function () {
			$btn.prop('disabled', false);
			setTimeout(function () { $status.text(''); }, 3000);
		});
	});
});
</script>
