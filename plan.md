# WPHZ UGC Carousel — Comprehensive Plugin Planning Document

> **Intended Audience:** Any developer/agent implementing this plugin from scratch.  
> **Stack:** WordPress + WooCommerce, PHP 8.0+, Composer, vanilla JS (no React/Vue in frontend), jQuery only where WP/WC already loads it.  
> **Shortcode:** `[wphz_ugc_carousel]`  
> **Plugin Slug:** `wphz-ugc-carousel`  
> **PHP Namespace Root:** `WPHZ\UGC`

---

## Table of Contents

1. [Architecture Principles](#1-architecture-principles)
2. [Directory Structure](#2-directory-structure)
3. [Database Schema](#3-database-schema)
4. [Phase 1 — Plugin Scaffold & Bootstrap](#phase-1--plugin-scaffold--bootstrap)
5. [Phase 2 — Data Layer (Repository + Transient)](#phase-2--data-layer-repository--transient)
6. [Phase 3 — Admin UI: Configuration Panel](#phase-3--admin-ui-configuration-panel)
7. [Phase 4 — Admin UI: Content Manager (Videos + Products)](#phase-4--admin-ui-content-manager-videos--products)
8. [Phase 5 — Shortcode Engine](#phase-5--shortcode-engine)
9. [Phase 6 — Frontend Templates & CSS](#phase-6--frontend-templates--css)
10. [Phase 7 — Frontend JavaScript (Carousel Engine)](#phase-7--frontend-javascript-carousel-engine)
11. [Phase 8 — WooCommerce Add-to-Cart Integration](#phase-8--woocommerce-add-to-cart-integration)
12. [Phase 9 — AJAX Handlers](#phase-9--ajax-handlers)
13. [Cross-Phase Connections & Contracts](#cross-phase-connections--contracts)
14. [Testing Checklist](#testing-checklist)

---

## 1. Architecture Principles

| Principle | How It Is Applied |
|---|---|
| **Singleton** | One `Plugin` base class; all service singletons extend `AbstractSingleton` |
| **Single Responsibility** | One file = one class = one concern. No HTML in logic files. |
| **Templates** | All markup lives in `/templates/`. Logic files call `TemplateLoader::render($template, $data)` |
| **DRY** | Shared logic extracted into `Helpers\*` and `Traits\*` |
| **Namespace** | `WPHZ\UGC` root. Composer PSR-4 autoload. |
| **WC-First Hooks** | Always prefer `woocommerce_*` hooks, filters, and class methods over raw WP equivalents |
| **Transient Cache** | Product search results cached with `WPHZ_UGC_product_search_{hash}` transients |

---

## 2. Directory Structure

```
wphz-ugc-carousel/
├── composer.json
├── composer.lock
├── wphz-ugc-carousel.php           ← Plugin entry point (only bootstraps)
├── vendor/                         ← Composer autoload
├── src/
│   ├── Plugin.php                  ← Singleton. Wires everything.
│   ├── AbstractSingleton.php       ← Base for all singletons
│   │
│   ├── Admin/
│   │   ├── AdminMenu.php           ← Registers WP admin menu page
│   │   ├── ConfigPage.php          ← Settings/Config tab logic
│   │   ├── ContentPage.php         ← Videos & Products tab logic
│   │   └── AssetLoader.php         ← Admin-side CSS/JS enqueue
│   │
│   ├── Ajax/
│   │   ├── SaveConfig.php          ← AJAX: save configuration
│   │   ├── SaveContent.php         ← AJAX: save carousel items
│   │   ├── ProductSearch.php       ← AJAX: search products (with transient)
│   │   └── DeleteItem.php          ← AJAX: delete a carousel line item
│   │
│   ├── Repository/
│   │   ├── CarouselRepository.php  ← CRUD for carousel config (wp_options)
│   │   └── ItemRepository.php      ← CRUD for carousel items (custom table)
│   │
│   ├── Shortcode/
│   │   ├── ShortcodeRegistrar.php  ← Registers [wphz_ugc_carousel]
│   │   └── ShortcodeRenderer.php   ← Resolves attrs, fetches data, calls template
│   │
│   ├── Frontend/
│   │   ├── AssetLoader.php         ← Frontend CSS/JS enqueue (conditional)
│   │   └── CartHandler.php         ← WC add-to-cart AJAX bridge
│   │
│   ├── Helpers/
│   │   ├── NonceHelper.php         ← create_nonce / verify_nonce wrappers
│   │   ├── SanitizeHelper.php      ← Input sanitization helpers
│   │   ├── TransientHelper.php     ← get/set/flush transient wrappers
│   │   └── TemplateLoader.php      ← render($template_name, $data) helper
│   │
│   └── Installer/
│       └── Installer.php           ← register_activation_hook logic, DB setup
│
└── templates/
    ├── admin/
    │   ├── layout.php              ← Admin page shell (tabs wrapper)
    │   ├── config-tab.php          ← Config form HTML
    │   └── content-tab.php         ← Content manager HTML
    └── frontend/
        ├── carousel-wrapper.php    ← Outer carousel section HTML
        ├── carousel-item.php       ← Single video slide HTML
        └── product-item.php        ← Single product card HTML (inside video)
```

---

## 3. Database Schema

### 3.1 `wp_wphz_ugc_items` (Custom Table)

```sql
CREATE TABLE wp_wphz_ugc_items (
  id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  carousel_id   VARCHAR(64)         NOT NULL DEFAULT 'default',
  sort_order    INT(11)             NOT NULL DEFAULT 0,
  video_id      BIGINT(20) UNSIGNED NOT NULL,       -- WP attachment ID
  video_url     TEXT                NOT NULL,
  product_ids   LONGTEXT            NOT NULL,        -- JSON array of WC product IDs
  created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY carousel_id (carousel_id),
  KEY sort_order  (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

> **Why `carousel_id`?** Reserves future multi-carousel support without schema changes. For now, default is always `'default'`.

### 3.2 `wp_options` Keys (Config)

| Option Key | Type | Description |
|---|---|---|
| `wphz_ugc_config` | JSON string | Heading, sub-heading, default mute, default slide direction |
| `wphz_ugc_danger_config` | JSON string | JS event/function names for arrow click events |
| `wphz_ugc_db_version` | string | Current DB schema version (for future migrations) |

---

## Phase 1 — Plugin Scaffold & Bootstrap

**Goal:** The plugin registers, autoloads, installs DB, and produces zero errors when activated.  
**No UI, no shortcode, no frontend yet.**

### 1.1 `composer.json`

```json
{
  "name": "wphz/ugc-carousel",
  "description": "UGC Video Carousel for WooCommerce",
  "type": "wordpress-plugin",
  "require": { "php": ">=8.0" },
  "autoload": {
    "psr-4": { "WPHZ\\UGC\\": "src/" }
  },
  "config": { "vendor-dir": "vendor" }
}
```

Run `composer install` before activating. The plugin entry point includes `vendor/autoload.php`.

### 1.2 `wphz-ugc-carousel.php` (Entry Point)

```php
<?php
/**
 * Plugin Name: WPHZ UGC Carousel
 * Plugin URI:  https://wphelpzone.com
 * Description: User-Generated Content video carousel with WooCommerce product attachment.
 * Version:     1.0.0
 * Author:      WPHelpZone LLC
 * Text Domain: wphz-ugc
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 */

defined('ABSPATH') || exit;

define('WPHZ_UGC_VERSION',   '1.0.0');
define('WPHZ_UGC_FILE',      __FILE__);
define('WPHZ_UGC_DIR',       plugin_dir_path(__FILE__));
define('WPHZ_UGC_URL',       plugin_dir_url(__FILE__));
define('WPHZ_UGC_TEMPLATES', WPHZ_UGC_DIR . 'templates/');

require_once WPHZ_UGC_DIR . 'vendor/autoload.php';

register_activation_hook(__FILE__, [\WPHZ\UGC\Installer\Installer::class, 'activate']);
register_deactivation_hook(__FILE__, [\WPHZ\UGC\Installer\Installer::class, 'deactivate']);

add_action('plugins_loaded', [\WPHZ\UGC\Plugin::class, 'instance']);
```

**Rules:**
- This file ONLY: defines constants, requires autoload, hooks activation/deactivation, and boots `Plugin::instance()`.
- Zero business logic here.

### 1.3 `AbstractSingleton.php`

```php
<?php
namespace WPHZ\UGC;

abstract class AbstractSingleton {
    private static array $instances = [];

    protected function __construct() {}
    private function __clone() {}

    public static function instance(): static {
        $class = static::class;
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }
        return self::$instances[$class];
    }
}
```

### 1.4 `Plugin.php`

```php
<?php
namespace WPHZ\UGC;

use WPHZ\UGC\Admin\AdminMenu;
use WPHZ\UGC\Shortcode\ShortcodeRegistrar;
use WPHZ\UGC\Frontend\AssetLoader as FrontendAssets;
use WPHZ\UGC\Ajax\SaveConfig;
use WPHZ\UGC\Ajax\SaveContent;
use WPHZ\UGC\Ajax\ProductSearch;
use WPHZ\UGC\Ajax\DeleteItem;
use WPHZ\UGC\Frontend\CartHandler;

final class Plugin extends AbstractSingleton {

    protected function __construct() {
        $this->init_hooks();
    }

    private function init_hooks(): void {
        // Guard: WooCommerce must be active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', [$this, 'notice_wc_missing']);
            return;
        }

        AdminMenu::instance()->init();
        ShortcodeRegistrar::instance()->init();
        FrontendAssets::instance()->init();
        CartHandler::instance()->init();

        // AJAX handlers
        SaveConfig::instance()->init();
        SaveContent::instance()->init();
        ProductSearch::instance()->init();
        DeleteItem::instance()->init();
    }

    private function is_woocommerce_active(): bool {
        return class_exists('WooCommerce');
    }

    public function notice_wc_missing(): void {
        echo '<div class="notice notice-error"><p>'
            . esc_html__('WPHZ UGC Carousel requires WooCommerce to be active.', 'wphz-ugc')
            . '</p></div>';
    }
}
```

### 1.5 `Installer/Installer.php`

```php
<?php
namespace WPHZ\UGC\Installer;

class Installer {

    public static function activate(): void {
        self::create_tables();
        self::set_default_config();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    private static function create_tables(): void {
        global $wpdb;
        $table   = $wpdb->prefix . 'wphz_ugc_items';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            carousel_id VARCHAR(64)         NOT NULL DEFAULT 'default',
            sort_order  INT(11)             NOT NULL DEFAULT 0,
            video_id    BIGINT(20) UNSIGNED NOT NULL,
            video_url   TEXT                NOT NULL,
            product_ids LONGTEXT            NOT NULL,
            created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY carousel_id (carousel_id),
            KEY sort_order  (sort_order)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option('wphz_ugc_db_version', WPHZ_UGC_VERSION);
    }

    private static function set_default_config(): void {
        if (!get_option('wphz_ugc_config')) {
            update_option('wphz_ugc_config', wp_json_encode([
                'heading'    => 'Real Talk From Real Fans',
                'subheading' => 'How real people enjoy the experience.',
                'mute'       => true,
                'direction'  => 'ltr',  // ltr = left-to-right slide, rtl = right-to-left
            ]));
        }
        if (!get_option('wphz_ugc_danger_config')) {
            update_option('wphz_ugc_danger_config', wp_json_encode([
                'on_arrow_right' => '',
                'on_arrow_left'  => '',
            ]));
        }
    }
}
```

**Cross-Phase Contract:** `wphz_ugc_config` option key and its JSON shape are the single source of truth referenced by Phase 2 (Repository), Phase 3 (ConfigPage), and Phase 5 (ShortcodeRenderer). **Never change this key name without updating all three.**

---

## Phase 2 — Data Layer (Repository + Transient)

**Goal:** Clean read/write API over DB and options. No AJAX, no UI. Other phases call these classes only.

**Rule:** Repositories are the ONLY classes that touch `$wpdb` or `get_option/update_option`.

### 2.1 `Repository/CarouselRepository.php`

```php
<?php
namespace WPHZ\UGC\Repository;

use WPHZ\UGC\AbstractSingleton;

class CarouselRepository extends AbstractSingleton {

    private const CONFIG_KEY  = 'wphz_ugc_config';
    private const DANGER_KEY  = 'wphz_ugc_danger_config';

    public function get_config(): array {
        $raw = get_option(self::CONFIG_KEY, '{}');
        return (array) json_decode($raw, true);
    }

    public function save_config(array $data): bool {
        return update_option(self::CONFIG_KEY, wp_json_encode($data));
    }

    public function get_danger_config(): array {
        $raw = get_option(self::DANGER_KEY, '{}');
        return (array) json_decode($raw, true);
    }

    public function save_danger_config(array $data): bool {
        return update_option(self::DANGER_KEY, wp_json_encode($data));
    }
}
```

### 2.2 `Repository/ItemRepository.php`

```php
<?php
namespace WPHZ\UGC\Repository;

use WPHZ\UGC\AbstractSingleton;

class ItemRepository extends AbstractSingleton {

    private function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'wphz_ugc_items';
    }

    /** @return array<int, array> */
    public function get_all(string $carousel_id = 'default'): array {
        global $wpdb;
        $table = $this->table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows  = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE carousel_id = %s ORDER BY sort_order ASC", $carousel_id),
            ARRAY_A
        );
        return $rows ?: [];
    }

    public function get_by_id(int $id): ?array {
        global $wpdb;
        $table = $this->table();
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        ) ?: null;
    }

    public function insert(array $data): int|false {
        global $wpdb;
        $result = $wpdb->insert($this->table(), [
            'carousel_id' => $data['carousel_id'] ?? 'default',
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'video_id'    => (int) $data['video_id'],
            'video_url'   => esc_url_raw($data['video_url']),
            'product_ids' => wp_json_encode(array_map('intval', $data['product_ids'] ?? [])),
        ]);
        return $result ? $wpdb->insert_id : false;
    }

    public function update(int $id, array $data): bool {
        global $wpdb;
        $fields = [];
        if (isset($data['sort_order']))  $fields['sort_order']  = (int) $data['sort_order'];
        if (isset($data['product_ids'])) $fields['product_ids'] = wp_json_encode(array_map('intval', $data['product_ids']));
        if (isset($data['video_url']))   $fields['video_url']   = esc_url_raw($data['video_url']);
        if (empty($fields)) return false;
        return (bool) $wpdb->update($this->table(), $fields, ['id' => $id]);
    }

    public function delete(int $id): bool {
        global $wpdb;
        return (bool) $wpdb->delete($this->table(), ['id' => $id]);
    }

    /** Bulk-update sort_order. $order = [item_id => new_sort_order, ...] */
    public function reorder(array $order): void {
        foreach ($order as $id => $sort) {
            $this->update((int) $id, ['sort_order' => (int) $sort]);
        }
    }
}
```

### 2.3 `Helpers/TransientHelper.php`

```php
<?php
namespace WPHZ\UGC\Helpers;

class TransientHelper {

    private const PREFIX      = 'wphz_ugc_';
    private const PRODUCT_TTL = 300; // 5 minutes

    public static function product_key(string $search_term): string {
        return self::PREFIX . 'product_' . md5(strtolower(trim($search_term)));
    }

    public static function get(string $key): mixed {
        $value = get_transient($key);
        return ($value === false) ? null : $value;
    }

    public static function set(string $key, mixed $value, int $expiry = self::PRODUCT_TTL): void {
        set_transient($key, $value, $expiry);
    }

    public static function flush_products(): void {
        // WooCommerce provides a helper to clear product transients
        wc_delete_product_transients();
        // flush our own search transients by clearing wp_options LIKE pattern
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . self::PREFIX . 'product_%'
            )
        );
    }
}
```

**Cross-Phase Contract:** `TransientHelper::product_key()` and `TransientHelper::flush_products()` are called exclusively by `Ajax/ProductSearch.php` (Phase 9). Any change to the key pattern must be reflected there.

---

## Phase 3 — Admin UI: Configuration Panel

**Goal:** Tabbed admin page. "Configuration" tab reads/writes heading, subheading, mute, direction, danger zone JS events.  
**Dependency:** Phase 1 (Plugin boots AdminMenu) + Phase 2 (CarouselRepository).

### 3.1 `Admin/AdminMenu.php`

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
        // Determine active tab — default is 'config'
        $active_tab = isset($_GET['tab'])
            ? sanitize_key($_GET['tab'])
            : 'config';

        \WPHZ\UGC\Helpers\TemplateLoader::render('admin/layout', [
            'active_tab' => $active_tab,
        ]);
    }
}
```

### 3.2 `Admin/AssetLoader.php`

```php
<?php
namespace WPHZ\UGC\Admin;

use WPHZ\UGC\AbstractSingleton;

class AssetLoader extends AbstractSingleton {
    // NOTE: AdminMenu calls AssetLoader::instance()->init() inside Plugin.php
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
        wp_enqueue_media(); // Required for WP media picker
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

> **Note:** `wp_enqueue_media()` must be called on the admin page load to enable the WordPress media picker in the Content tab (Phase 4).

### 3.3 `Admin/ConfigPage.php`

```php
<?php
namespace WPHZ\UGC\Admin;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Repository\CarouselRepository;

class ConfigPage extends AbstractSingleton {
    // Provides data to the config-tab template. No output here.

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

### 3.4 `templates/admin/layout.php`

```php
<?php defined('ABSPATH') || exit; ?>
<div class="wrap wphz-ugc-admin">
    <h1><?php esc_html_e('WPHZ UGC Carousel', 'wphz-ugc'); ?></h1>
    <nav class="nav-tab-wrapper">
        <a href="?page=wphz-ugc-carousel&tab=config"
           class="nav-tab <?php echo $active_tab === 'config' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Configuration', 'wphz-ugc'); ?>
        </a>
        <a href="?page=wphz-ugc-carousel&tab=content"
           class="nav-tab <?php echo $active_tab === 'content' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Content', 'wphz-ugc'); ?>
        </a>
    </nav>
    <div class="tab-content">
        <?php if ($active_tab === 'config'): ?>
            <?php \WPHZ\UGC\Helpers\TemplateLoader::render(
                'admin/config-tab',
                \WPHZ\UGC\Admin\ConfigPage::instance()->get_data()
            ); ?>
        <?php elseif ($active_tab === 'content'): ?>
            <?php \WPHZ\UGC\Helpers\TemplateLoader::render(
                'admin/content-tab',
                \WPHZ\UGC\Admin\ContentPage::instance()->get_data()
            ); ?>
        <?php endif; ?>
    </div>
</div>
```

### 3.5 `templates/admin/config-tab.php`

```php
<?php defined('ABSPATH') || exit; ?>
<form id="wphz-ugc-config-form" data-action="wphz_ugc_save_config">
    <?php wp_nonce_field('wphz_ugc_admin', 'wphz_nonce'); ?>

    <table class="form-table" role="presentation">
        <tr>
            <th><label for="wphz_heading"><?php esc_html_e('Heading', 'wphz-ugc'); ?></label></th>
            <td><input type="text" id="wphz_heading" name="heading"
                       value="<?php echo esc_attr($heading); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><label for="wphz_subheading"><?php esc_html_e('Sub Heading', 'wphz-ugc'); ?></label></th>
            <td><input type="text" id="wphz_subheading" name="subheading"
                       value="<?php echo esc_attr($subheading); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Default Sound', 'wphz-ugc'); ?></th>
            <td>
                <label>
                    <input type="radio" name="mute" value="1" <?php checked($mute, true); ?>>
                    <?php esc_html_e('Mute', 'wphz-ugc'); ?>
                </label>
                <label style="margin-left:16px">
                    <input type="radio" name="mute" value="0" <?php checked($mute, false); ?>>
                    <?php esc_html_e('Unmute', 'wphz-ugc'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e('Default Slide Direction', 'wphz-ugc'); ?></th>
            <td>
                <label>
                    <input type="radio" name="direction" value="ltr" <?php checked($direction, 'ltr'); ?>>
                    <?php esc_html_e('Left to Right', 'wphz-ugc'); ?>
                </label>
                <label style="margin-left:16px">
                    <input type="radio" name="direction" value="rtl" <?php checked($direction, 'rtl'); ?>>
                    <?php esc_html_e('Right to Left', 'wphz-ugc'); ?>
                </label>
            </td>
        </tr>
    </table>

    <hr>
    <div class="wphz-danger-zone">
        <h2 style="color:#c0392b;">⚠ <?php esc_html_e('Danger Zone — Event Hooks', 'wphz-ugc'); ?></h2>
        <p class="description">
            <?php esc_html_e('Enter a JS function name or custom event name to fire when arrows are clicked.', 'wphz-ugc'); ?>
        </p>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e('Right Arrow Click → JS Function/Event', 'wphz-ugc'); ?></th>
                <td><input type="text" name="on_arrow_right"
                           value="<?php echo esc_attr($on_arrow_right); ?>"
                           placeholder="e.g. myTrackingFn or my_custom_event"
                           class="regular-text"></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Left Arrow Click → JS Function/Event', 'wphz-ugc'); ?></th>
                <td><input type="text" name="on_arrow_left"
                           value="<?php echo esc_attr($on_arrow_left); ?>"
                           placeholder="e.g. myTrackingFn or my_custom_event"
                           class="regular-text"></td>
            </tr>
        </table>
    </div>

    <p class="submit">
        <button type="submit" class="button button-primary">
            <?php esc_html_e('Save Configuration', 'wphz-ugc'); ?>
        </button>
        <span class="wphz-save-status"></span>
    </p>
</form>
```

**How Danger Zone events are fired in JS (documented here for Phase 7):**

> When the right arrow is clicked, the carousel JS will:  
> 1. Check `window.wphzUGCConfig.on_arrow_right` (injected via `wp_localize_script` in Phase 5/7)  
> 2. If it matches a `typeof window[name] === 'function'`, call it with `(direction, slideIndex)` args  
> 3. Also dispatch `document.dispatchEvent(new CustomEvent(name, { detail: {...} }))` so both patterns work

---

## Phase 4 — Admin UI: Content Manager (Videos + Products)

**Goal:** Admin can add video line items, attach products, drag to reorder, and delete items. On save, items are persisted to DB and a shortcode is displayed.  
**Dependency:** Phase 2 (ItemRepository), Phase 1 (AssetLoader with `wp_enqueue_media`).

### 4.1 `Admin/ContentPage.php`

```php
<?php
namespace WPHZ\UGC\Admin;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Repository\ItemRepository;

class ContentPage extends AbstractSingleton {

    public function get_data(): array {
        $items = ItemRepository::instance()->get_all('default');

        // Decode product_ids JSON for each item
        $items = array_map(function (array $item): array {
            $item['product_ids'] = json_decode($item['product_ids'], true) ?: [];
            return $item;
        }, $items);

        return [
            'items'     => $items,
            'shortcode' => $this->build_shortcode(),
        ];
    }

    private function build_shortcode(): string {
        $config = \WPHZ\UGC\Repository\CarouselRepository::instance()->get_config();
        $mute   = !empty($config['mute']) ? 'mute' : 'unmute';
        $dir    = $config['direction'] ?? 'ltr';
        return sprintf('[wphz_ugc_carousel sound="%s" slide="%s"]', $mute, $dir);
    }
}
```

### 4.2 `templates/admin/content-tab.php`

Key UI elements this template must render:

```php
<?php defined('ABSPATH') || exit; ?>
<div class="wphz-content-manager">

    <!-- Shortcode display -->
    <div class="wphz-shortcode-box">
        <label><?php esc_html_e('Your Shortcode:', 'wphz-ugc'); ?></label>
        <code id="wphz-shortcode"><?php echo esc_html($shortcode); ?></code>
        <button class="button" id="wphz-copy-shortcode"><?php esc_html_e('Copy', 'wphz-ugc'); ?></button>
    </div>

    <!-- Add new video button -->
    <button class="button button-secondary" id="wphz-add-item">
        + <?php esc_html_e('Add Video from Media Library', 'wphz-ugc'); ?>
    </button>

    <!-- Line items list (sortable via jquery-ui-sortable) -->
    <ul id="wphz-items-list" class="wphz-items-list">
        <?php foreach ($items as $item): ?>
            <?php \WPHZ\UGC\Helpers\TemplateLoader::render('admin/item-row', [
                'item' => $item,
            ]); ?>
        <?php endforeach; ?>
    </ul>

    <!-- Save all items -->
    <p class="submit">
        <button class="button button-primary" id="wphz-save-content">
            <?php esc_html_e('Save & Get Shortcode', 'wphz-ugc'); ?>
        </button>
        <span class="wphz-save-status"></span>
    </p>
</div>
```

### 4.3 `templates/admin/item-row.php`

Each item row contains:
- Drag handle
- Video thumbnail preview
- Hidden: `video_id`, `video_url`
- Product search dropdown (Select2-style using WC's built-in `wc-product-search`)
- Selected products list (with remove buttons)
- Delete row button

```php
<?php defined('ABSPATH') || exit;
// $item = array with keys: id, video_id, video_url, product_ids (array of ints)
$video_id    = (int) ($item['video_id'] ?? 0);
$video_url   = $item['video_url'] ?? '';
$product_ids = $item['product_ids'] ?? [];
$row_id      = $item['id'] ?? 'new-' . uniqid();
?>
<li class="wphz-item-row" data-id="<?php echo esc_attr($row_id); ?>">
    <span class="dashicons dashicons-move wphz-drag-handle"></span>

    <div class="wphz-item-preview">
        <?php if ($video_url): ?>
            <video src="<?php echo esc_url($video_url); ?>" width="120" muted playsinline preload="metadata"></video>
        <?php else: ?>
            <span class="wphz-no-video"><?php esc_html_e('No video selected', 'wphz-ugc'); ?></span>
        <?php endif; ?>
        <input type="hidden" name="items[<?php echo esc_attr($row_id); ?>][video_id]"
               value="<?php echo esc_attr($video_id); ?>">
        <input type="hidden" name="items[<?php echo esc_attr($row_id); ?>][video_url]"
               value="<?php echo esc_url($video_url); ?>">
    </div>

    <div class="wphz-item-products">
        <label><?php esc_html_e('Attached Products', 'wphz-ugc'); ?></label>
        <!-- WooCommerce product search input — resolved via AJAX (Phase 9) -->
        <div class="wphz-product-search-wrap">
            <input type="text" class="wphz-product-search"
                   placeholder="<?php esc_attr_e('Search products...', 'wphz-ugc'); ?>"
                   data-row="<?php echo esc_attr($row_id); ?>">
            <ul class="wphz-product-suggestions"></ul>
        </div>
        <!-- Selected products chips -->
        <ul class="wphz-selected-products">
            <?php foreach ($product_ids as $pid): ?>
                <?php
                $product = wc_get_product($pid);
                if (!$product) continue;
                ?>
                <li data-id="<?php echo (int) $pid; ?>">
                    <?php echo esc_html($product->get_name()); ?>
                    <input type="hidden"
                           name="items[<?php echo esc_attr($row_id); ?>][product_ids][]"
                           value="<?php echo (int) $pid; ?>">
                    <button type="button" class="wphz-remove-product">×</button>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <button type="button" class="button wphz-delete-item" data-id="<?php echo esc_attr($row_id); ?>">
        <?php esc_html_e('Remove', 'wphz-ugc'); ?>
    </button>
</li>
```

**Product Search Strategy:**  
- The admin JS listens to `keyup` on `.wphz-product-search` (debounced 300ms).  
- It fires `wphz_ugc_product_search` AJAX action (Phase 9).  
- Results render as a dropdown `<ul>` with product name + thumbnail.  
- Clicking a result adds a chip to `.wphz-selected-products` with a hidden `<input>`.  
- WooCommerce's own `wc_get_product()` is used server-side to fetch product data.  
- Transients cache the search results for 5 min (Phase 2, `TransientHelper`).

---

## Phase 5 — Shortcode Engine

**Goal:** Register `[wphz_ugc_carousel]`, parse attributes (falling back to DB defaults), fetch carousel items, and pass everything to the frontend template renderer.  
**Dependency:** Phase 2 (both Repositories), Phase 6 (templates).

### 5.1 Shortcode Attribute Contract

| Attribute | Values | Default Source |
|---|---|---|
| `sound` | `"mute"` / `"unmute"` | `wphz_ugc_config.mute` |
| `slide` | `"ltr"` / `"rtl"` | `wphz_ugc_config.direction` |
| `carousel_id` | any string | `"default"` |

**Rule:** Shortcode attributes OVERRIDE display only. They never write back to saved config.

### 5.2 `Shortcode/ShortcodeRegistrar.php`

```php
<?php
namespace WPHZ\UGC\Shortcode;

use WPHZ\UGC\AbstractSingleton;

class ShortcodeRegistrar extends AbstractSingleton {

    public function init(): void {
        add_shortcode('wphz_ugc_carousel', [ShortcodeRenderer::instance(), 'render']);
    }
}
```

### 5.3 `Shortcode/ShortcodeRenderer.php`

```php
<?php
namespace WPHZ\UGC\Shortcode;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Repository\CarouselRepository;
use WPHZ\UGC\Repository\ItemRepository;
use WPHZ\UGC\Helpers\TemplateLoader;

class ShortcodeRenderer extends AbstractSingleton {

    public function render(array $atts): string {
        $config  = CarouselRepository::instance()->get_config();
        $danger  = CarouselRepository::instance()->get_danger_config();

        // Merge shortcode atts over defaults from DB
        $atts = shortcode_atts([
            'sound'       => $config['mute'] ? 'mute' : 'unmute',
            'slide'       => $config['direction'] ?? 'ltr',
            'carousel_id' => 'default',
        ], $atts, 'wphz_ugc_carousel');

        $items = ItemRepository::instance()->get_all($atts['carousel_id']);
        $items = $this->hydrate_items($items);

        if (empty($items)) return '';

        // Enqueue frontend assets
        \WPHZ\UGC\Frontend\AssetLoader::instance()->enqueue_now($atts, $danger);

        return TemplateLoader::render_return('frontend/carousel-wrapper', [
            'heading'    => $config['heading']    ?? '',
            'subheading' => $config['subheading'] ?? '',
            'items'      => $items,
            'sound'      => $atts['sound'],
            'slide'      => $atts['slide'],
            'is_muted'   => $atts['sound'] === 'mute',
        ]);
    }

    /**
     * Decode product_ids and attach WC product objects for template use.
     */
    private function hydrate_items(array $items): array {
        return array_map(function (array $item): array {
            $pids            = json_decode($item['product_ids'], true) ?: [];
            $item['products'] = $this->fetch_products($pids);
            return $item;
        }, $items);
    }

    private function fetch_products(array $pids): array {
        $products = [];
        foreach ($pids as $pid) {
            $product = wc_get_product((int) $pid);
            if ($product && $product->is_visible()) {
                $products[] = $product;
            }
        }
        return $products;
    }
}
```

---

## Phase 6 — Frontend Templates & CSS

**Goal:** Pixel-matching HTML output vs. the reference images. Pure HTML + CSS (+ BEM class naming). Zero JS in templates.  
**Dependency:** Phase 5 provides template data. Phase 7 will attach JS behavior to CSS classes/data-attrs defined here.

### 6.1 Class / Data-Attribute Contract (used by Phase 7 JS)

| HTML element | Class / Attr | Purpose |
|---|---|---|
| Outer wrapper | `.wphz-ugc-carousel` | Root scope |
| Track (slides row) | `.wphz-ugc-track` | Transform target for sliding |
| Single slide | `.wphz-ugc-slide` | Each video item |
| Active/center slide | `.wphz-ugc-slide--active` | JS adds/removes |
| Video element | `.wphz-ugc-video` | JS controls play/pause/mute |
| Mute button | `.wphz-ugc-mute-btn` | Per-slide toggle |
| Arrow right | `.wphz-ugc-arrow--right` | Nav trigger |
| Arrow left | `.wphz-ugc-arrow--left` | Nav trigger |
| Product strip | `.wphz-ugc-products` | Under each slide |
| Product slide | `.wphz-ugc-product-item` | Individual product card |
| Add to cart | `.wphz-ugc-atc-btn` | WC ATC AJAX trigger |
| `data-product-id` | on `.wphz-ugc-atc-btn` | WC product ID |

### 6.2 `templates/frontend/carousel-wrapper.php`

```php
<?php defined('ABSPATH') || exit; ?>
<section class="wphz-ugc-carousel" 
         data-muted="<?php echo $is_muted ? '1' : '0'; ?>"
         data-direction="<?php echo esc_attr($slide); ?>">

    <div class="wphz-ugc-header">
        <div class="wphz-ugc-header__text">
            <?php if ($heading): ?>
                <h2 class="wphz-ugc-heading"><?php echo esc_html($heading); ?></h2>
            <?php endif; ?>
            <?php if ($subheading): ?>
                <p class="wphz-ugc-subheading"><?php echo esc_html($subheading); ?></p>
            <?php endif; ?>
        </div>
        <div class="wphz-ugc-arrows">
            <button class="wphz-ugc-arrow wphz-ugc-arrow--left" aria-label="Previous">&#8592;</button>
            <button class="wphz-ugc-arrow wphz-ugc-arrow--right" aria-label="Next">&#8594;</button>
        </div>
    </div>

    <div class="wphz-ugc-stage">
        <div class="wphz-ugc-track">
            <?php foreach ($items as $index => $item): ?>
                <?php \WPHZ\UGC\Helpers\TemplateLoader::render('frontend/carousel-item', [
                    'item'     => $item,
                    'index'    => $index,
                    'is_muted' => $is_muted,
                    'active'   => $index === 0,
                ]); ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
```

### 6.3 `templates/frontend/carousel-item.php`

```php
<?php defined('ABSPATH') || exit;
$products     = $item['products'] ?? [];
$multi_product = count($products) > 1;
?>
<div class="wphz-ugc-slide <?php echo $active ? 'wphz-ugc-slide--active' : ''; ?>"
     data-index="<?php echo (int) $index; ?>">

    <div class="wphz-ugc-video-wrap">
        <video class="wphz-ugc-video"
               src="<?php echo esc_url($item['video_url']); ?>"
               <?php echo $is_muted ? 'muted' : ''; ?>
               playsinline
               preload="<?php echo $active ? 'auto' : 'metadata'; ?>"
               loop="false">
        </video>
        <button class="wphz-ugc-mute-btn" aria-label="Toggle sound">
            <span class="wphz-icon-mute"><?php /* SVG muted icon */ ?></span>
            <span class="wphz-icon-unmute" style="display:none"><?php /* SVG sound icon */ ?></span>
        </button>
    </div>

    <?php if (!empty($products)): ?>
        <div class="wphz-ugc-products <?php echo $multi_product ? 'wphz-ugc-products--carousel' : ''; ?>">
            <div class="wphz-ugc-products-track">
                <?php foreach ($products as $product): ?>
                    <?php \WPHZ\UGC\Helpers\TemplateLoader::render('frontend/product-item', [
                        'product' => $product,
                    ]); ?>
                <?php endforeach; ?>
            </div>
            <?php if ($multi_product): ?>
                <button class="wphz-product-arrow wphz-product-arrow--prev">&#8249;</button>
                <button class="wphz-product-arrow wphz-product-arrow--next">&#8250;</button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
```

### 6.4 `templates/frontend/product-item.php`

```php
<?php defined('ABSPATH') || exit; ?>
<div class="wphz-ugc-product-item">
    <div class="wphz-ugc-product-img">
        <?php echo $product->get_image('thumbnail'); ?>
    </div>
    <div class="wphz-ugc-product-info">
        <span class="wphz-ugc-product-name">
            <?php echo esc_html($product->get_name()); ?>
        </span>
        <span class="wphz-ugc-product-price">
            <?php echo wp_kses_post($product->get_price_html()); ?>
        </span>
    </div>
    <button class="wphz-ugc-atc-btn"
            data-product-id="<?php echo (int) $product->get_id(); ?>"
            data-nonce="<?php echo esc_attr(wp_create_nonce('wphz_ugc_atc')); ?>">
        <?php esc_html_e('Add to Cart', 'wphz-ugc'); ?>
    </button>
</div>
```

### 6.5 CSS Architecture (`assets/frontend/carousel.css`)

Key CSS rules to implement (no framework):

```css
/* Stage clips overflow; track slides via translateX */
.wphz-ugc-stage        { overflow: hidden; position: relative; }
.wphz-ugc-track        { display: flex; transition: transform 0.4s cubic-bezier(0.25,0.46,0.45,0.94); will-change: transform; }

/* Slides: 3 visible on desktop, 1 on mobile */
.wphz-ugc-slide        { flex: 0 0 calc(100% / 3); padding: 0 8px; box-sizing: border-box; }

/* Center slide (active) scales up slightly */
.wphz-ugc-slide--active .wphz-ugc-video-wrap { transform: scale(1.04); }

/* Video fills slide */
.wphz-ugc-video        { width: 100%; aspect-ratio: 9/16; object-fit: cover; border-radius: 12px; }

/* Product strip */
.wphz-ugc-products     { display: flex; align-items: center; gap: 8px; padding: 8px 0; overflow: hidden; }
.wphz-ugc-products--carousel { position: relative; }

/* Mobile */
@media (max-width: 768px) {
    .wphz-ugc-slide    { flex: 0 0 80%; }
}
```

---

## Phase 7 — Frontend JavaScript (Carousel Engine)

**Goal:** Implement all carousel behaviors as a self-contained ES6 class in a single file. The class reads DOM data-attributes and CSS classes defined in Phase 6. It has ZERO coupling to PHP classes — it only reads `wphzUGCFrontend` localized object.  
**Dependency:** Phase 6 (DOM contract), Phase 3 (Danger Zone events are injected via `wp_localize_script`).

### 7.1 `assets/frontend/carousel.js` — Architecture

```javascript
/**
 * WPHZUGCCarousel — Carousel Engine
 * Single class, single file. All methods are helpers — no monolithic methods.
 */
class WPHZUGCCarousel {

    /* ── Constructor ── */
    constructor(el) {
        this.root        = el;
        this.track       = el.querySelector('.wphz-ugc-track');
        this.slides      = Array.from(el.querySelectorAll('.wphz-ugc-slide'));
        this.totalSlides = this.slides.length;
        this.current     = 0; // index of active/center slide
        this.isMuted     = el.dataset.muted === '1';
        this.direction   = el.dataset.direction || 'ltr'; // 'ltr' | 'rtl'

        // Drag state
        this._drag = { active: false, startX: 0, diffX: 0 };

        this.init();
    }

    /* ── Init: wire all event listeners ── */
    init() {
        this._bindArrows();
        this._bindDrag();
        this._bindMuteButtons();
        this._autoPlayCurrent();
        this._updateSlideClasses();
    }

    /* ── Slide Navigation ── */
    goTo(index) {
        this._pauseVideo(this.current); // pause old
        this.current = this._clamp(index);
        this._updateSlideClasses();
        this._applyTransform();
        this._autoPlayCurrent();
    }

    next() { this.goTo(this.current + 1); }
    prev() { this.goTo(this.current - 1); }

    _clamp(index) {
        if (index < 0) return this.totalSlides - 1;
        if (index >= this.totalSlides) return 0;
        return index;
    }

    /* ── Transform ── */
    _applyTransform() {
        // Center slide is always current; offset so active appears centered
        // On desktop: 3 visible, active in middle, so offset = (current - 1) slides
        const visibleCount = this._getVisibleCount();
        const offset       = this.current - Math.floor(visibleCount / 2);
        const pct          = (100 / visibleCount) * offset;
        this.track.style.transform = `translateX(-${pct}%)`;
    }

    _getVisibleCount() {
        return window.innerWidth <= 768 ? 1 : 3;
    }

    /* ── CSS Class Management ── */
    _updateSlideClasses() {
        this.slides.forEach((slide, i) => {
            slide.classList.toggle('wphz-ugc-slide--active', i === this.current);
        });
    }

    /* ── Video Control ── */
    _autoPlayCurrent() {
        const video = this._getVideoAt(this.current);
        if (!video) return;
        video.muted = this.isMuted;
        video.play().catch(() => {});
        video.onended = () => this._onVideoEnded();
    }

    _pauseVideo(index) {
        const video = this._getVideoAt(index);
        if (video) { video.pause(); video.currentTime = 0; }
    }

    _getVideoAt(index) {
        return this.slides[index]?.querySelector('.wphz-ugc-video') || null;
    }

    _onVideoEnded() {
        // Auto-slide based on direction
        this.direction === 'rtl' ? this.prev() : this.next();
    }

    /* ── Arrow Buttons ── */
    _bindArrows() {
        const rightBtn = this.root.querySelector('.wphz-ugc-arrow--right');
        const leftBtn  = this.root.querySelector('.wphz-ugc-arrow--left');

        rightBtn?.addEventListener('click', () => {
            this.next();
            this._fireDangerEvent('right');
        });
        leftBtn?.addEventListener('click', () => {
            this.prev();
            this._fireDangerEvent('left');
        });
    }

    /* ── Danger Zone Event Firing ── */
    _fireDangerEvent(direction) {
        const config = window.wphzUGCFrontend?.danger || {};
        const name   = direction === 'right' ? config.on_arrow_right : config.on_arrow_left;
        if (!name) return;

        const detail = { direction, slideIndex: this.current };

        // 1. Dispatch as CustomEvent (works for addEventListener listeners)
        document.dispatchEvent(new CustomEvent(name, { detail }));

        // 2. If it's also a global function, call it
        if (typeof window[name] === 'function') {
            window[name](direction, this.current);
        }
    }

    /* ── Drag / Swipe ── */
    _bindDrag() {
        const track = this.track;

        // Mouse
        track.addEventListener('mousedown', e => this._onDragStart(e.clientX));
        window.addEventListener('mousemove', e => this._onDragMove(e.clientX));
        window.addEventListener('mouseup',   ()  => this._onDragEnd());

        // Touch
        track.addEventListener('touchstart', e => this._onDragStart(e.touches[0].clientX), { passive: true });
        track.addEventListener('touchmove',  e => this._onDragMove(e.touches[0].clientX),  { passive: true });
        track.addEventListener('touchend',   ()  => this._onDragEnd());
    }

    _onDragStart(x) {
        this._drag = { active: true, startX: x, diffX: 0 };
        this.track.style.transition = 'none';
    }

    _onDragMove(x) {
        if (!this._drag.active) return;
        this._drag.diffX = x - this._drag.startX;
    }

    _onDragEnd() {
        if (!this._drag.active) return;
        this._drag.active = false;
        this.track.style.transition = '';

        const threshold = 60; // px
        if (this._drag.diffX < -threshold) this.next();
        else if (this._drag.diffX > threshold) this.prev();
        else this._applyTransform(); // snap back
    }

    /* ── Per-Slide Mute Toggle ── */
    _bindMuteButtons() {
        this.root.addEventListener('click', e => {
            const btn = e.target.closest('.wphz-ugc-mute-btn');
            if (!btn) return;
            const slide = btn.closest('.wphz-ugc-slide');
            const video = slide?.querySelector('.wphz-ugc-video');
            if (!video) return;
            video.muted = !video.muted;
            btn.querySelector('.wphz-icon-mute').style.display  = video.muted ? '' : 'none';
            btn.querySelector('.wphz-icon-unmute').style.display = video.muted ? 'none' : '';
        });
    }
}

/* ── Product Sub-Carousel ── */
class WPHZProductCarousel {
    constructor(el) {
        this.wrap    = el;
        this.track   = el.querySelector('.wphz-ugc-products-track');
        this.items   = Array.from(el.querySelectorAll('.wphz-ugc-product-item'));
        this.current = 0;
        this._bindArrows();
    }

    _bindArrows() {
        this.wrap.querySelector('.wphz-product-arrow--next')
            ?.addEventListener('click', () => this._slide(1));
        this.wrap.querySelector('.wphz-product-arrow--prev')
            ?.addEventListener('click', () => this._slide(-1));
    }

    _slide(dir) {
        const max    = this.items.length - 1;
        this.current = Math.max(0, Math.min(max, this.current + dir));
        const pct    = this.current * 100;
        this.track.style.transform = `translateX(-${pct}%)`;
    }
}

/* ── Boot on DOMContentLoaded ── */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.wphz-ugc-carousel').forEach(el => {
        new WPHZUGCCarousel(el);
    });
    document.querySelectorAll('.wphz-ugc-products--carousel').forEach(el => {
        new WPHZProductCarousel(el);
    });
});
```

---

## Phase 8 — WooCommerce Add-to-Cart Integration

**Goal:** ATC buttons in product cards fire WC's AJAX add-to-cart without page reload, show feedback, and update the WC cart fragment.  
**Dependency:** WooCommerce must be active (guarded in Phase 1). Phase 6 template renders buttons with `data-product-id` and `data-nonce`.

### 8.1 `Frontend/CartHandler.php`

```php
<?php
namespace WPHZ\UGC\Frontend;

use WPHZ\UGC\AbstractSingleton;

class CartHandler extends AbstractSingleton {

    public function init(): void {
        // WC provides wc_ajax_add_to_cart — we use it directly via JS.
        // This class only adds a nonce verify layer if we need a custom endpoint.
        // For simple products: WC's own AJAX endpoint is sufficient.
        // We only need a custom handler for grouped/variable products.
        add_action('wp_ajax_wphz_ugc_add_to_cart',        [$this, 'handle']);
        add_action('wp_ajax_nopriv_wphz_ugc_add_to_cart', [$this, 'handle']);
    }

    public function handle(): void {
        \WPHZ\UGC\Helpers\NonceHelper::verify('wphz_ugc_atc');

        $product_id = (int) ($_POST['product_id'] ?? 0);
        $quantity   = (int) ($_POST['quantity']   ?? 1);

        if (!$product_id) wp_send_json_error(['message' => 'Invalid product.']);

        $product = wc_get_product($product_id);
        if (!$product || !$product->is_purchasable()) {
            wp_send_json_error(['message' => 'Product not purchasable.']);
        }

        // Use WC cart add method
        $result = WC()->cart->add_to_cart($product_id, $quantity);

        if ($result) {
            // Trigger WC cart fragments update
            WC_AJAX::get_refreshed_fragments();  // Sends JSON and exits
        } else {
            wp_send_json_error(['message' => 'Could not add to cart.']);
        }
    }
}
```

### 8.2 ATC JS (appended to `carousel.js`)

```javascript
/* ── Add to Cart ── */
document.addEventListener('click', e => {
    const btn = e.target.closest('.wphz-ugc-atc-btn');
    if (!btn) return;

    const productId = btn.dataset.productId;
    const nonce     = btn.dataset.nonce;
    const original  = btn.textContent;

    btn.textContent  = '...';
    btn.disabled     = true;

    const body = new URLSearchParams({
        action:     'wphz_ugc_add_to_cart',
        product_id: productId,
        quantity:   1,
        nonce,
    });

    fetch(wphzUGCFrontend.ajaxurl, { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                btn.textContent = wphzUGCFrontend.i18n.added;
                // Trigger WC fragments refresh
                document.body.dispatchEvent(new CustomEvent('wc_fragment_refresh'));
            } else {
                btn.textContent = wphzUGCFrontend.i18n.error;
            }
        })
        .catch(() => { btn.textContent = wphzUGCFrontend.i18n.error; })
        .finally(() => {
            setTimeout(() => {
                btn.textContent = original;
                btn.disabled    = false;
            }, 2500);
        });
});
```

---

## Phase 9 — AJAX Handlers

**Goal:** Four AJAX endpoints. Each class handles EXACTLY one action. Input sanitized via `SanitizeHelper`. Nonce verified via `NonceHelper`.

### 9.1 `Helpers/NonceHelper.php`

```php
<?php
namespace WPHZ\UGC\Helpers;

class NonceHelper {
    public static function verify(string $action): void {
        $nonce = sanitize_text_field($_REQUEST['nonce'] ?? $_REQUEST['wphz_nonce'] ?? '');
        if (!wp_verify_nonce($nonce, $action)) {
            wp_send_json_error(['message' => 'Security check failed.'], 403);
        }
    }
}
```

### 9.2 `Ajax/SaveConfig.php`

```php
<?php
namespace WPHZ\UGC\Ajax;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\NonceHelper;
use WPHZ\UGC\Helpers\SanitizeHelper;
use WPHZ\UGC\Repository\CarouselRepository;

class SaveConfig extends AbstractSingleton {

    public function init(): void {
        add_action('wp_ajax_wphz_ugc_save_config', [$this, 'handle']);
    }

    public function handle(): void {
        NonceHelper::verify('wphz_ugc_admin');

        $config = [
            'heading'    => SanitizeHelper::text($_POST['heading']    ?? ''),
            'subheading' => SanitizeHelper::text($_POST['subheading'] ?? ''),
            'mute'       => !empty($_POST['mute']) && $_POST['mute'] === '1',
            'direction'  => in_array($_POST['direction'] ?? '', ['ltr', 'rtl']) ? $_POST['direction'] : 'ltr',
        ];
        $danger = [
            'on_arrow_right' => SanitizeHelper::js_identifier($_POST['on_arrow_right'] ?? ''),
            'on_arrow_left'  => SanitizeHelper::js_identifier($_POST['on_arrow_left']  ?? ''),
        ];

        $repo = CarouselRepository::instance();
        $repo->save_config($config);
        $repo->save_danger_config($danger);

        wp_send_json_success(['message' => __('Configuration saved.', 'wphz-ugc')]);
    }
}
```

### 9.3 `Ajax/SaveContent.php`

```php
<?php
namespace WPHZ\UGC\Ajax;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\NonceHelper;
use WPHZ\UGC\Repository\ItemRepository;

class SaveContent extends AbstractSingleton {

    public function init(): void {
        add_action('wp_ajax_wphz_ugc_save_content', [$this, 'handle']);
    }

    public function handle(): void {
        NonceHelper::verify('wphz_ugc_admin');

        $items_raw = $_POST['items'] ?? [];
        $repo      = ItemRepository::instance();

        // Delete all existing for carousel_id = 'default', re-insert
        // (Simple replace-all strategy for now; extend for multi-carousel in v2)
        $this->clear_existing($repo);

        $sort = 0;
        foreach ($items_raw as $row_id => $data) {
            $video_id   = (int) ($data['video_id']  ?? 0);
            $video_url  = esc_url_raw($data['video_url'] ?? '');
            $product_ids = array_map('intval', (array) ($data['product_ids'] ?? []));

            if (!$video_id || !$video_url) continue;

            $repo->insert([
                'carousel_id' => 'default',
                'sort_order'  => $sort++,
                'video_id'    => $video_id,
                'video_url'   => $video_url,
                'product_ids' => $product_ids,
            ]);
        }

        wp_send_json_success(['message' => __('Content saved.', 'wphz-ugc')]);
    }

    private function clear_existing(ItemRepository $repo): void {
        global $wpdb;
        $table = $wpdb->prefix . 'wphz_ugc_items';
        $wpdb->delete($table, ['carousel_id' => 'default']);
    }
}
```

### 9.4 `Ajax/ProductSearch.php`

```php
<?php
namespace WPHZ\UGC\Ajax;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\NonceHelper;
use WPHZ\UGC\Helpers\TransientHelper;

class ProductSearch extends AbstractSingleton {

    public function init(): void {
        add_action('wp_ajax_wphz_ugc_product_search', [$this, 'handle']);
    }

    public function handle(): void {
        NonceHelper::verify('wphz_ugc_admin');

        $term    = sanitize_text_field($_GET['term'] ?? '');
        if (strlen($term) < 2) wp_send_json_success([]);

        $key     = TransientHelper::product_key($term);
        $cached  = TransientHelper::get($key);

        if ($cached !== null) {
            wp_send_json_success($cached);
        }

        // Use WC's own product query
        $query = new \WC_Product_Query([
            'limit'   => 15,
            'status'  => 'publish',
            's'       => $term,
            'orderby' => 'relevance',
            'return'  => 'objects',
        ]);

        $products = $query->get_products();
        $results  = array_map(fn($p) => [
            'id'        => $p->get_id(),
            'name'      => $p->get_name(),
            'price_html'=> wp_strip_all_tags($p->get_price_html()),
            'thumbnail' => wp_get_attachment_image_url($p->get_image_id(), 'thumbnail'),
        ], $products);

        TransientHelper::set($key, $results);
        wp_send_json_success($results);
    }
}
```

### 9.5 `Ajax/DeleteItem.php`

```php
<?php
namespace WPHZ\UGC\Ajax;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\NonceHelper;
use WPHZ\UGC\Repository\ItemRepository;

class DeleteItem extends AbstractSingleton {

    public function init(): void {
        add_action('wp_ajax_wphz_ugc_delete_item', [$this, 'handle']);
    }

    public function handle(): void {
        NonceHelper::verify('wphz_ugc_admin');
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error();
        $ok = ItemRepository::instance()->delete($id);
        $ok ? wp_send_json_success() : wp_send_json_error();
    }
}
```

---

## Cross-Phase Connections & Contracts

This section is **critical**. Mismatches here cause silent bugs.

### C1: Config Option Key Contract
> **Key:** `wphz_ugc_config` / **Shape:** `{heading, subheading, mute(bool), direction('ltr'|'rtl')}`  
> **Writers:** `Installer::set_default_config()` (Phase 1), `Ajax/SaveConfig` (Phase 9)  
> **Readers:** `CarouselRepository::get_config()` (Phase 2), `ShortcodeRenderer::render()` (Phase 5), `ContentPage::build_shortcode()` (Phase 4)  
> **Rule:** Never read this option directly with `get_option()` outside `CarouselRepository`.

### C2: DB Table Column Contract
> **Table:** `wp_wphz_ugc_items` / `product_ids` column is **JSON string**, never comma-separated.  
> **All decoders** use `json_decode($item['product_ids'], true) ?: []`.  
> **Writers:** `ItemRepository::insert()`, `ItemRepository::update()`  
> **Readers:** `ItemRepository::get_all()` → `ShortcodeRenderer::hydrate_items()` → templates  

### C3: CSS Class / Data-Attr Contract (Phase 6 → Phase 7)
> Phase 7 JS is coupled **only** to the HTML classes listed in §6.1.  
> If Phase 6 renames a class, Phase 7 must update the same string.  
> Use the table in §6.1 as the single canonical reference.

### C4: `wp_localize_script` Data Contract (Phase 5/7 → JS)
> `Frontend/AssetLoader::enqueue_now()` must pass this exact object:
```php
wp_localize_script('wphz-ugc-frontend', 'wphzUGCFrontend', [
    'ajaxurl'   => admin_url('admin-ajax.php'),
    'nonce'     => wp_create_nonce('wphz_ugc_atc'),
    'danger'    => $danger, // ['on_arrow_right' => '...', 'on_arrow_left' => '...']
    'i18n'      => [
        'added' => __('Added!',         'wphz-ugc'),
        'error' => __('Error — retry.', 'wphz-ugc'),
    ],
]);
```
> The JS reads `window.wphzUGCFrontend.danger`, `window.wphzUGCFrontend.ajaxurl`, etc.  
> Changing any key name here breaks both PHP and JS simultaneously.

### C5: AJAX Action Name Contract

| PHP `add_action` | JS `action:` field | Handler Class |
|---|---|---|
| `wphz_ugc_save_config` | `wphz_ugc_save_config` | `Ajax/SaveConfig` |
| `wphz_ugc_save_content` | `wphz_ugc_save_content` | `Ajax/SaveContent` |
| `wphz_ugc_product_search` | `wphz_ugc_product_search` | `Ajax/ProductSearch` |
| `wphz_ugc_delete_item` | `wphz_ugc_delete_item` | `Ajax/DeleteItem` |
| `wphz_ugc_add_to_cart` | `wphz_ugc_add_to_cart` | `Frontend/CartHandler` |

### C6: Nonce Action String Contract

| Nonce Action String | Where Created | Where Verified |
|---|---|---|
| `wphz_ugc_admin` | `Admin/AssetLoader::enqueue()` via `wp_localize_script` | All `Ajax/Save*` and `Ajax/Delete*` handlers |
| `wphz_ugc_atc` | `templates/frontend/product-item.php` per button | `Frontend/CartHandler::handle()` |

---

## `Helpers/TemplateLoader.php`

```php
<?php
namespace WPHZ\UGC\Helpers;

class TemplateLoader {

    /**
     * Render a template by name, extracting $data into local scope.
     * Outputs directly (echo).
     */
    public static function render(string $template, array $data = []): void {
        $file = WPHZ_UGC_TEMPLATES . ltrim($template, '/') . '.php';
        if (!file_exists($file)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
            trigger_error("WPHZ UGC: Template not found: {$file}", E_USER_WARNING);
            return;
        }
        extract($data, EXTR_SKIP); // EXTR_SKIP: never overwrite existing vars
        include $file;
    }

    /**
     * Same as render() but returns output as string.
     */
    public static function render_return(string $template, array $data = []): string {
        ob_start();
        self::render($template, $data);
        return ob_get_clean();
    }
}
```

---

## `Helpers/SanitizeHelper.php`

```php
<?php
namespace WPHZ\UGC\Helpers;

class SanitizeHelper {

    public static function text(string $value): string {
        return sanitize_text_field($value);
    }

    /**
     * Sanitize a JS identifier or event name (alphanumeric, underscore, dash only).
     * Used for Danger Zone fields to prevent XSS via localized script output.
     */
    public static function js_identifier(string $value): string {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $value);
    }

    public static function url(string $value): string {
        return esc_url_raw($value);
    }

    public static function int_array(array $values): array {
        return array_map('intval', $values);
    }
}
```

---

## `Frontend/AssetLoader.php`

```php
<?php
namespace WPHZ\UGC\Frontend;

use WPHZ\UGC\AbstractSingleton;

class AssetLoader extends AbstractSingleton {

    private bool $enqueued = false;

    public function init(): void {
        // Assets are lazy-enqueued only when shortcode is rendered (enqueue_now())
        // Nothing registered globally to avoid loading on every page.
    }

    /**
     * Called by ShortcodeRenderer when shortcode is actually present on page.
     */
    public function enqueue_now(array $atts, array $danger): void {
        if ($this->enqueued) return;
        $this->enqueued = true;

        wp_enqueue_style(
            'wphz-ugc-frontend',
            WPHZ_UGC_URL . 'assets/frontend/carousel.css',
            ['woocommerce-general'], // depend on WC stylesheet for price formatting
            WPHZ_UGC_VERSION
        );
        wp_enqueue_script(
            'wphz-ugc-frontend',
            WPHZ_UGC_URL . 'assets/frontend/carousel.js',
            [], // no jQuery dependency — vanilla JS
            WPHZ_UGC_VERSION,
            true // footer
        );
        wp_localize_script('wphz-ugc-frontend', 'wphzUGCFrontend', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wphz_ugc_atc'),
            'danger'  => $danger,
            'i18n'    => [
                'added' => __('Added!',         'wphz-ugc'),
                'error' => __('Error — retry.', 'wphz-ugc'),
            ],
        ]);
    }
}
```

---

## Testing Checklist

### Phase 1
- [ ] Plugin activates without PHP errors on WP 6.0+ / PHP 8.0+
- [ ] `wp_wphz_ugc_items` table is created on activation
- [ ] `wphz_ugc_config` option created with defaults
- [ ] Plugin admin notice fires when WooCommerce is inactive

### Phase 2
- [ ] `ItemRepository::insert()` → `get_all()` round-trip preserves product_ids JSON
- [ ] `TransientHelper::get()` returns null (not false) on cache miss
- [ ] `TransientHelper::flush_products()` clears WC transients + plugin transients

### Phase 3
- [ ] Config tab renders correct form values from DB
- [ ] Danger zone fields only save alphanumeric+underscore+dash characters

### Phase 4
- [ ] Media picker opens and returns video attachment ID + URL
- [ ] Product search autocomplete fires after 2 chars (debounced)
- [ ] Selecting product adds chip with hidden input
- [ ] Drag-reorder updates `sort_order` on save
- [ ] Shortcode is correctly generated and copyable

### Phase 5
- [ ] `[wphz_ugc_carousel]` uses DB defaults when no attrs given
- [ ] `sound="unmute"` overrides mute default without changing saved config
- [ ] `slide="rtl"` overrides direction without changing saved config
- [ ] Empty carousel (no items) returns empty string (no broken HTML)

### Phase 6
- [ ] Desktop: 3 slides visible, center slide scaled up
- [ ] Mobile (≤768px): 1 slide takes ~80% width
- [ ] Product section: single product = full width static; multiple = sub-carousel

### Phase 7
- [ ] Center video autoplays on load (muted by default)
- [ ] When video ends → next slide auto-advances
- [ ] Arrow right → advances forward; Arrow left → goes back
- [ ] Drag right → prev; Drag left → next (above 60px threshold)
- [ ] Mute toggle per slide works independently
- [ ] Danger zone: if `on_arrow_right` = `myFn` and `window.myFn` exists → called
- [ ] Danger zone: `CustomEvent` dispatched regardless of whether global function exists

### Phase 8
- [ ] ATC button adds product to WC cart via AJAX (no page reload)
- [ ] WC cart fragment updates (mini-cart count refreshes)
- [ ] Button shows "Added!" then resets after 2.5s
- [ ] Non-purchasable product returns error JSON gracefully

### Phase 9
- [ ] All AJAX handlers return 403 on nonce failure
- [ ] `ProductSearch` returns cached results on second identical query
- [ ] `SaveContent` replaces all items (idempotent — saving twice doesn't duplicate)

---

## Implementation Order (Strict)

```
Phase 1 → Phase 2 → Phase 9 → Phase 3 → Phase 4 → Phase 5 → Phase 6 → Phase 7 → Phase 8
```

Each phase can be tested in isolation before proceeding. The only cross-phase dependency that must be resolved before moving forward is: **Phase 2 must complete before Phase 3, 4, 5, and 9** since all of them delegate persistence to the Repository classes.

---

*Document version: 1.0.0 | Author: WPHelpZone LLC*