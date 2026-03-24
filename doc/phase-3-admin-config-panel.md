# Phase 3 — Admin UI: Configuration Panel

> **Goal:** Tabbed admin page. "Configuration" tab reads/writes heading, subheading, mute, direction, and danger zone JS events.  
> **Dependencies:** Phase 1 (Plugin boots AdminMenu) + Phase 2 (CarouselRepository)

---

## Implementation Order

```
Phase 1 ✅ → Phase 2 ✅ → Phase 9 ✅ → [Phase 3] → Phase 4 → Phase 5 → Phase 6 → Phase 7 → Phase 8
```

---

## Files to Create

| # | File Path | Purpose |
|---|-----------|---------|
| 1 | `src/Admin/AdminMenu.php` | Registers WP admin menu page |
| 2 | `src/Admin/AssetLoader.php` | Admin-side CSS/JS enqueue |
| 3 | `src/Admin/ConfigPage.php` | Settings/Config tab logic (data provider) |
| 4 | `src/Helpers/TemplateLoader.php` | Template rendering helper |
| 5 | `templates/admin/layout.php` | Admin page shell (tabs wrapper) |
| 6 | `templates/admin/config-tab.php` | Config form HTML |

---

## Checklist

### 3.1 — `src/Admin/AdminMenu.php`
- [ ] Namespace: `WPHZ\UGC\Admin`
- [ ] Extends `AbstractSingleton`
- [ ] `init()` → hooks `admin_menu` action
- [ ] `register_menu()` → uses `add_menu_page()`:
  - Page title: `'UGC Carousel'`
  - Menu title: `'UGC Carousel'`
  - Capability: `'manage_options'`
  - Slug: `'wphz-ugc-carousel'`
  - Icon: `'dashicons-video-alt3'`
  - Position: `58`
- [ ] `render_page()`:
  - [ ] Sanitize `$_GET['tab']` with `sanitize_key()`
  - [ ] Default tab: `'config'`
  - [ ] Call `TemplateLoader::render('admin/layout', ['active_tab' => $active_tab])`

```php
<?php
namespace WPHZ\UGC\Admin;

use WPHZ\UGC\AbstractSingleton;

class AdminMenu extends AbstractSingleton {

    public function init(): void {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu(): void {
        add_menu_page(
            __('UGC Carousel', 'wphz-ugc'),
            __('UGC Carousel', 'wphz-ugc'),
            'manage_options',
            'wphz-ugc-carousel',
            [$this, 'render_page'],
            'dashicons-video-alt3',
            58
        );
    }

    public function render_page(): void {
        $active_tab = isset($_GET['tab'])
            ? sanitize_key($_GET['tab'])
            : 'config';

        \WPHZ\UGC\Helpers\TemplateLoader::render('admin/layout', [
            'active_tab' => $active_tab,
        ]);
    }
}
```

### 3.2 — `src/Admin/AssetLoader.php`
- [ ] Namespace: `WPHZ\UGC\Admin`
- [ ] Extends `AbstractSingleton`
- [ ] `init()` → hooks `admin_enqueue_scripts`
- [ ] `enqueue(string $hook)`:
  - [ ] Guard: return early if `$hook` doesn't contain `'wphz-ugc-carousel'`
  - [ ] Enqueue CSS: `assets/admin/admin.css`
  - [ ] Call `wp_enqueue_media()` (required for WP media picker in Phase 4)
  - [ ] Enqueue JS: `assets/admin/admin.js` with deps `['jquery', 'jquery-ui-sortable']`, in footer
  - [ ] `wp_localize_script()` → expose `wphzUGC` object:
    - `ajaxurl` → `admin_url('admin-ajax.php')`
    - `nonce` → `wp_create_nonce('wphz_ugc_admin')`
    - `mediaTitle` → `'Select Video'`
    - `mediaButton` → `'Use this video'`

```php
<?php
namespace WPHZ\UGC\Admin;

use WPHZ\UGC\AbstractSingleton;

class AssetLoader extends AbstractSingleton {

    public function init(): void {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(string $hook): void {
        if (!str_contains($hook, 'wphz-ugc-carousel')) return;

        wp_enqueue_style(
            'wphz-ugc-admin',
            WPHZ_UGC_URL . 'assets/admin/admin.css',
            [],
            WPHZ_UGC_VERSION
        );
        wp_enqueue_media();
        wp_enqueue_script(
            'wphz-ugc-admin',
            WPHZ_UGC_URL . 'assets/admin/admin.js',
            ['jquery', 'jquery-ui-sortable'],
            WPHZ_UGC_VERSION,
            true
        );
        wp_localize_script('wphz-ugc-admin', 'wphzUGC', [
            'ajaxurl'       => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('wphz_ugc_admin'),
            'mediaTitle'    => __('Select Video', 'wphz-ugc'),
            'mediaButton'   => __('Use this video', 'wphz-ugc'),
        ]);
    }
}
```

> **Note:** `wp_enqueue_media()` must be called on the admin page load to enable the WordPress media picker in Phase 4's Content tab.

### 3.3 — `src/Admin/ConfigPage.php`
- [ ] Namespace: `WPHZ\UGC\Admin`
- [ ] Extends `AbstractSingleton`
- [ ] `get_data(): array` — **provides data only, no output here**
  - [ ] Read config via `CarouselRepository::instance()->get_config()`
  - [ ] Read danger config via `CarouselRepository::instance()->get_danger_config()`
  - [ ] Return array with keys: `heading`, `subheading`, `mute` (bool), `direction`, `on_arrow_right`, `on_arrow_left`

```php
<?php
namespace WPHZ\UGC\Admin;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Repository\CarouselRepository;

class ConfigPage extends AbstractSingleton {

    public function get_data(): array {
        $config = CarouselRepository::instance()->get_config();
        $danger = CarouselRepository::instance()->get_danger_config();
        return [
            'heading'        => $config['heading']    ?? '',
            'subheading'     => $config['subheading'] ?? '',
            'mute'           => (bool) ($config['mute'] ?? true),
            'direction'      => $config['direction']  ?? 'ltr',
            'on_arrow_right' => $danger['on_arrow_right'] ?? '',
            'on_arrow_left'  => $danger['on_arrow_left']  ?? '',
        ];
    }
}
```

### 3.4 — `src/Helpers/TemplateLoader.php`
- [ ] Namespace: `WPHZ\UGC\Helpers`
- [ ] Static class
- [ ] `render(string $template, array $data = []): void`
  - [ ] Build file path: `WPHZ_UGC_TEMPLATES . ltrim($template, '/') . '.php'`
  - [ ] Check file exists, trigger `E_USER_WARNING` if not
  - [ ] `extract($data, EXTR_SKIP)` — never overwrite existing vars
  - [ ] `include` the template file
- [ ] `render_return(string $template, array $data = []): string`
  - [ ] Output buffer version — returns string instead of echoing

```php
<?php
namespace WPHZ\UGC\Helpers;

class TemplateLoader {

    public static function render(string $template, array $data = []): void {
        $file = WPHZ_UGC_TEMPLATES . ltrim($template, '/') . '.php';
        if (!file_exists($file)) {
            trigger_error("WPHZ UGC: Template not found: {$file}", E_USER_WARNING);
            return;
        }
        extract($data, EXTR_SKIP);
        include $file;
    }

    public static function render_return(string $template, array $data = []): string {
        ob_start();
        self::render($template, $data);
        return ob_get_clean();
    }
}
```

### 3.5 — `templates/admin/layout.php`
- [ ] ABSPATH guard
- [ ] Outer wrapper: `<div class="wrap wphz-ugc-admin">`
- [ ] Page title: `<h1>WPHZ UGC Carousel</h1>`
- [ ] Tab navigation with `nav-tab-wrapper`:
  - [ ] "Configuration" tab → `?page=wphz-ugc-carousel&tab=config`
  - [ ] "Content" tab → `?page=wphz-ugc-carousel&tab=content`
  - [ ] Active tab gets `nav-tab-active` class
- [ ] Tab content area:
  - [ ] If `config` tab → render `admin/config-tab` template with `ConfigPage::instance()->get_data()`
  - [ ] If `content` tab → render `admin/content-tab` template with `ContentPage::instance()->get_data()`

### 3.6 — `templates/admin/config-tab.php`
- [ ] ABSPATH guard
- [ ] Form with `id="wphz-ugc-config-form"` and `data-action="wphz_ugc_save_config"`
- [ ] WordPress nonce field: `wp_nonce_field('wphz_ugc_admin', 'wphz_nonce')`
- [ ] Config fields in `<table class="form-table">`:
  - [ ] **Heading** — text input, `name="heading"`, prefilled from `$heading`
  - [ ] **Sub Heading** — text input, `name="subheading"`, prefilled from `$subheading`
  - [ ] **Default Sound** — radio buttons (`mute` / `unmute`), `name="mute"`, values `1` / `0`
  - [ ] **Default Slide Direction** — radio buttons (`ltr` / `rtl`), `name="direction"`
- [ ] Danger Zone section (`<div class="wphz-danger-zone">`):
  - [ ] Styled heading with ⚠ icon and red color
  - [ ] Description text about JS function/event names
  - [ ] **Right Arrow Click** — text input, `name="on_arrow_right"`, placeholder example
  - [ ] **Left Arrow Click** — text input, `name="on_arrow_left"`, placeholder example
- [ ] Submit button: `"Save Configuration"` with `class="button button-primary"`
- [ ] Status span: `<span class="wphz-save-status"></span>`

### 3.7 — Asset Files (Create Stubs)
- [ ] Create `assets/admin/admin.css` (empty/stub for now, content in Phase 4)
- [ ] Create `assets/admin/admin.js` (stub — at minimum handle config form AJAX submit)

---

## Admin JS: Config Form Submit (in `assets/admin/admin.js`)

The admin JS must handle the config form submission via AJAX:

```javascript
jQuery(function($) {
    // Config form AJAX save
    $('#wphz-ugc-config-form').on('submit', function(e) {
        e.preventDefault();
        const $form   = $(this);
        const $status = $form.find('.wphz-save-status');

        $.post(wphzUGC.ajaxurl, {
            action:         $form.data('action'),
            nonce:          wphzUGC.nonce,
            heading:        $form.find('[name="heading"]').val(),
            subheading:     $form.find('[name="subheading"]').val(),
            mute:           $form.find('[name="mute"]:checked').val(),
            direction:      $form.find('[name="direction"]:checked').val(),
            on_arrow_right: $form.find('[name="on_arrow_right"]').val(),
            on_arrow_left:  $form.find('[name="on_arrow_left"]').val(),
        })
        .done(function(res) {
            $status.text(res.data?.message || 'Saved!').css('color', 'green');
        })
        .fail(function() {
            $status.text('Error saving.').css('color', 'red');
        })
        .always(function() {
            setTimeout(() => $status.text(''), 3000);
        });
    });
});
```

---

## Danger Zone Event Firing (documented for Phase 7 reference)

> When the right arrow is clicked, the carousel JS will:  
> 1. Check `window.wphzUGCFrontend.danger.on_arrow_right` (injected via `wp_localize_script` in Phase 5/7)  
> 2. If it matches a `typeof window[name] === 'function'`, call it with `(direction, slideIndex)` args  
> 3. Also dispatch `document.dispatchEvent(new CustomEvent(name, { detail: {...} }))` so both patterns work

---

## Verification

- [ ] Admin menu item "UGC Carousel" appears in WP admin sidebar
- [ ] Config tab renders correct form values from DB defaults
- [ ] Tab switching between "Configuration" and "Content" works
- [ ] Danger zone fields only save alphanumeric+underscore+dash characters (validated by Phase 9 SaveConfig)
- [ ] Admin CSS and JS only load on the plugin's admin page (not globally)
- [ ] `wp_enqueue_media()` is called (verifiable by media picker availability)
