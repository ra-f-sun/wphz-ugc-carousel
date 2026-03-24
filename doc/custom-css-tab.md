# Custom CSS Tab — Complete Change Set

---

## 1. src/Installer/Installer.php

Add the `custom_css` column to the CREATE TABLE statement, and add an
ALTER TABLE migration for existing installs (dbDelta cannot add columns).

### In `create_tables()` — add column to `$sql_carousels`:

```php
$sql_carousels = "CREATE TABLE {$table_carousels} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    heading varchar(255),
    subheading varchar(255),
    mute tinyint(1) DEFAULT 1,
    direction varchar(10) DEFAULT 'ltr',
    on_arrow_right varchar(255),
    on_arrow_left varchar(255),
    custom_css longtext DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (id)
) {$charset};";
```

### After the two `dbDelta()` calls, add the migration block:

```php
// Migration: add custom_css column to existing installs
if ( ! $wpdb->get_var("SHOW COLUMNS FROM {$table_carousels} LIKE 'custom_css'") ) {
    $wpdb->query("ALTER TABLE {$table_carousels} ADD COLUMN custom_css longtext DEFAULT NULL");
}
```

---

## 2. src/Repository/CarouselRepository.php

Add `custom_css` to `insert()` and `update()`.

### In `insert()` — add to the array passed to `$wpdb->insert()`:

```php
'custom_css' => isset($data['custom_css'])
    ? wp_strip_all_tags($data['custom_css'])
    : null,
```

### In `update()` — add the conditional field:

```php
if (array_key_exists('custom_css', $data)) {
    $fields['custom_css'] = wp_strip_all_tags($data['custom_css']);
}
```

Note: `array_key_exists` is used (not `isset`) so that saving an empty
string '' correctly clears the CSS rather than being ignored.

---

## 3. src/Ajax/SaveCustomCss.php ← NEW FILE

```php
<?php
namespace WPHZ\UGC\Ajax;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\NonceHelper;
use WPHZ\UGC\Repository\CarouselRepository;

class SaveCustomCss extends AbstractSingleton {

    public function init(): void {
        add_action('wp_ajax_wphz_ugc_save_custom_css', [$this, 'handle']);
    }

    public function handle(): void {
        NonceHelper::verify('wphz_ugc_admin');

        $carousel_id = (int) ($_POST['carousel_id'] ?? 0);
        if ($carousel_id <= 0) {
            wp_send_json_error(['message' => __('Invalid Carousel ID.', 'wphz-ugc')]);
        }

        // wp_strip_all_tags: removes </style> injections and all HTML.
        // Preserves valid CSS syntax. Appropriate for admin-only input.
        $css = wp_strip_all_tags(wp_unslash($_POST['custom_css'] ?? ''));

        $updated = CarouselRepository::instance()->update($carousel_id, [
            'custom_css' => $css,
        ]);

        if ($updated !== false) {
            wp_send_json_success(['message' => __('Custom CSS saved.', 'wphz-ugc')]);
        } else {
            wp_send_json_error(['message' => __('Failed to save.', 'wphz-ugc')]);
        }
    }
}
```

---

## 4. Register SaveCustomCss in Plugin.php

Wherever your other Ajax classes are initialised (likely in `Plugin.php`
inside `init_hooks()` or similar), add:

```php
\WPHZ\UGC\Ajax\SaveCustomCss::instance()->init();
```

---

## 5. templates/admin/layout.php

Add the third tab link and elseif block.

### Tab nav — add after the Content tab `<a>`:

```php
<a href="?page=wphz-ugc-carousel&action=edit&id=<?php echo esc_attr($id); ?>&tab=custom-css"
   class="nav-tab <?php echo $active_tab === 'custom-css' ? 'nav-tab-active' : ''; ?>">
    <?php esc_html_e('Custom CSS', 'wphz-ugc'); ?>
</a>
```

### Tab content — add after the `elseif ($active_tab === 'content')` block:

```php
<?php elseif ($active_tab === 'custom-css'): ?>
    <?php \WPHZ\UGC\Helpers\TemplateLoader::render(
        'admin/custom-css-tab',
        \WPHZ\UGC\Admin\ConfigPage::instance()->get_data($id)
    ); ?>
```

Note: `ConfigPage::get_data($id)` already returns the full carousel row
from the DB (including the new `custom_css` column) so no new method is
needed.

---

## 6. templates/admin/custom-css-tab.php ← NEW FILE

```php
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
        <button type="submit" class="button button-primary">
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
    wp.codeEditor.initialize(document.getElementById('wphz_custom_css'), {
        codemirror: {
            mode: 'css',
            lineNumbers: true,
            lineWrapping: true,
            indentUnit: 4,
        }
    });
});
</script>
```

---

## 7. src/Admin/AssetLoader.php

Enqueue the WordPress CodeMirror editor assets only when the custom-css
tab is active. Add this inside the existing admin asset enqueue method,
guarded by a tab check:

```php
<?php
namespace WPHZ\UGC\Admin;

use WPHZ\UGC\AbstractSingleton;

class AssetLoader extends AbstractSingleton {

    public function init(): void {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(string $hook): void {
        // Only load on this plugin's admin page
        if (!str_contains($hook, 'wphz-ugc-carousel')) {
            return;
        }

        wp_enqueue_style(
            'wphz-ugc-admin',
            WPHZ_UGC_URL . 'assets/admin/admin.css',
            [],
            WPHZ_UGC_VERSION
        );

        // Required for the WP media picker used in the Content tab (Phase 4)
        wp_enqueue_media();

        wp_enqueue_script(
            'wphz-ugc-admin',
            WPHZ_UGC_URL . 'assets/admin/admin.js',
            ['jquery', 'jquery-ui-sortable'],
            WPHZ_UGC_VERSION,
            true // footer
        );

        wp_localize_script('wphz-ugc-admin', 'wphzUGC', [
            'ajaxurl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('wphz_ugc_admin'),
            'mediaTitle'  => __('Select Video', 'wphz-ugc'),
            'mediaButton' => __('Use this video', 'wphz-ugc'),
        ]);

        // ── CodeMirror for Custom CSS tab ─────────────────────────────────────
        // Only enqueue when the custom-css tab is actually being viewed.
        // wp_enqueue_code_editor() is a no-op (returns false) if the user has
        // disabled syntax highlighting in their profile — safe either way.
        if (
            ($GLOBALS['_GET']['action'] ?? '') === 'edit' &&
            ($GLOBALS['_GET']['tab']    ?? '') === 'custom-css'
        ) {
            wp_enqueue_code_editor(['type' => 'text/css']);
        }
    }
}
```

No additional JS is needed — the inline `<script>` in `custom-css-tab.php`
handles CodeMirror initialisation using the `wp.codeEditor` global that
the above enqueue provides.

---

## 8. src/Shortcode/ShortcodeRenderer.php

Output a scoped `<style>` tag prepended to the shortcode HTML.

`wp_add_inline_style()` cannot be used here because shortcodes render
during the content, which is after `wp_head()` has already fired and
flushed enqueued styles. A `<style>` tag in the HTML is the correct
approach for this case.

### Replace the `enqueue_now` call + return block:

```php
// Lazy-enqueue frontend assets
\WPHZ\UGC\Frontend\AssetLoader::instance()->enqueue_now($config);

// Prepend scoped custom CSS if the carousel has any
$custom_css_output = '';
$raw_css = trim($config['custom_css'] ?? '');
if ($raw_css !== '') {
    $custom_css_output = sprintf(
        '<style id="wphz-carousel-css-%1$d">%2$s</style>',
        $id,
        $raw_css   // already sanitized via wp_strip_all_tags() on save
    );
}

return $custom_css_output . TemplateLoader::render_return('frontend/carousel-wrapper', [
    'id'       => $id,
    'items'    => $items,
    'sound'    => !empty($config['mute']) ? 'mute' : 'unmute',
    'slide'    => $config['direction'] ?? 'ltr',
    'is_muted' => !empty($config['mute']),
]);
```

---

## Summary of all files touched

| File                                    | Action                                       |
| --------------------------------------- | -------------------------------------------- |
| `src/Installer/Installer.php`           | Add column to schema + ALTER TABLE migration |
| `src/Repository/CarouselRepository.php` | Add `custom_css` to insert/update            |
| `src/Ajax/SaveCustomCss.php`            | **NEW** — dedicated Ajax handler             |
| `Plugin.php`                            | Register `SaveCustomCss::instance()->init()` |
| `templates/admin/layout.php`            | Add third tab + elseif                       |
| `templates/admin/custom-css-tab.php`    | **NEW** — form with CodeMirror textarea      |
| `src/Admin/AssetLoader.php`             | Enqueue CodeMirror on custom-css tab         |
| `src/Shortcode/ShortcodeRenderer.php`   | Prepend scoped `<style>` to output           |
