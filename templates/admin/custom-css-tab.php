<?php defined('ABSPATH') || exit; ?>
<form id="wphz-ugc-custom-css-form" data-action="wphz_ugc_save_custom_css">
    <?php wp_nonce_field('wphz_ugc_admin', 'wphz_nonce'); ?>
    <input type="hidden" name="carousel_id" value="<?php echo esc_attr($id); ?>">

    <p style="margin-top: 1.5rem; color: #555;">
        <?php esc_html_e(
            'Write CSS that applies only to this carousel. Use the selector shown in the hint below to target this carousel specifically.',
            'wphz-ugc'
        ); ?>
    </p>

    <p>
        <code style="background:#f0f0f0; padding: 4px 8px; border-radius:3px; font-size:13px;">
            [data-carousel-id="<?php echo esc_attr($id); ?>"] .wphz-ugc-video-wrap { }
        </code>
    </p>

    <div style="margin-top: 1rem;">
        <textarea
            id="wphz_custom_css"
            name="custom_css"
            rows="20"
            style="width:100%; font-family:monospace; font-size:13px;"
        ><?php echo esc_textarea($custom_css ?? ''); ?></textarea>
    </div>

    <p class="submit">
        <button type="submit" class="button button-primary" id="wphz-save-custom-css">
            <?php esc_html_e('Save CSS', 'wphz-ugc'); ?>
        </button>
        <span class="wphz-save-status"></span>
    </p>
</form>

<script>
// Activate WordPress CodeMirror on the textarea (CSS mode).
// wp_enqueue_code_editor() is called by the admin AssetLoader when this tab is active.
document.addEventListener('DOMContentLoaded', function () {
    if (typeof wp === 'undefined' || !wp.codeEditor) return;
    
    // Store the editor instance so we can manually sync it later
    var editor = wp.codeEditor.initialize(document.getElementById('wphz_custom_css'), {
        codemirror: {
            mode: 'css',
            lineNumbers: true,
            lineWrapping: true,
            indentUnit: 4,
        }
    });

    // Custom CSS Save Handler
    var $form = jQuery('#wphz-ugc-custom-css-form');
    $form.on('submit', function (e) {
        e.preventDefault();
        
        // CodeMirror intercepts the textarea. If we preventDefault(), it doesn't auto-sync!
        // We MUST force it to save its current buffer back into the textarea before reading .val()
        if (editor && editor.codemirror) {
            editor.codemirror.save();
        }

        var $btn = jQuery('#wphz-save-custom-css');
        var $status = $form.find('.wphz-save-status');
        
        $btn.prop('disabled', true);
        
        jQuery.post(wphzUGC.ajaxurl, {
            action:      $form.data('action'),
            nonce:       wphzUGC.nonce,
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
