# Phase 1 — Plugin Scaffold & Bootstrap

> **Goal:** The plugin registers, autoloads, installs DB, and produces zero errors when activated.  
> **No UI, no shortcode, no frontend yet.**  
> **Dependencies:** None (first phase)

---

## Implementation Order

```
Phase 1 → Phase 2 → Phase 9 → Phase 3 → Phase 4 → Phase 5 → Phase 6 → Phase 7 → Phase 8
```

---

## Files to Create

| # | File Path | Purpose |
|---|-----------|---------|
| 1 | `composer.json` | Composer config with PSR-4 autoload for `WPHZ\UGC\` → `src/` |
| 2 | `wphz-ugc-carousel.php` | Plugin entry point (only bootstraps) |
| 3 | `src/AbstractSingleton.php` | Base singleton pattern for all service classes |
| 4 | `src/Plugin.php` | Main plugin class — wires everything |
| 5 | `src/Installer/Installer.php` | Activation/deactivation hook logic, DB table creation |

---

## Checklist

### 1.1 — `composer.json`
- [ ] Create `composer.json` at project root
- [ ] Set `"name": "wphz/ugc-carousel"`
- [ ] Set `"type": "wordpress-plugin"`
- [ ] Set `"require": { "php": ">=8.0" }`
- [ ] Set PSR-4 autoload: `"WPHZ\\UGC\\": "src/"`
- [ ] Set `"config": { "vendor-dir": "vendor" }`
- [ ] Run `composer install` to generate `vendor/autoload.php`

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

### 1.2 — `wphz-ugc-carousel.php` (Entry Point)
- [ ] Add standard WordPress plugin header comment block:
  - Plugin Name: `WPHZ UGC Carousel`
  - Plugin URI: `https://wphelpzone.com`
  - Description: `User-Generated Content video carousel with WooCommerce product attachment.`
  - Version: `1.0.0`
  - Author: `WPHelpZone LLC`
  - Text Domain: `wphz-ugc`
  - Requires at least: `6.0`
  - Requires PHP: `8.0`
  - WC requires at least: `7.0`
- [ ] Add `defined('ABSPATH') || exit;` guard
- [ ] Define constants:
  - `WPHZ_UGC_VERSION` → `'1.0.0'`
  - `WPHZ_UGC_FILE` → `__FILE__`
  - `WPHZ_UGC_DIR` → `plugin_dir_path(__FILE__)`
  - `WPHZ_UGC_URL` → `plugin_dir_url(__FILE__)`
  - `WPHZ_UGC_TEMPLATES` → `WPHZ_UGC_DIR . 'templates/'`
- [ ] Require `vendor/autoload.php`
- [ ] Register activation hook → `Installer::activate`
- [ ] Register deactivation hook → `Installer::deactivate`
- [ ] Hook `plugins_loaded` → `Plugin::instance()`
- [ ] **Rule:** This file ONLY defines constants, requires autoload, hooks activation/deactivation, and boots `Plugin::instance()`. Zero business logic here.

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

### 1.3 — `src/AbstractSingleton.php`
- [ ] Create file at `src/AbstractSingleton.php`
- [ ] Namespace: `WPHZ\UGC`
- [ ] Declare as `abstract class`
- [ ] Private static `$instances` array
- [ ] Protected constructor (prevent external instantiation)
- [ ] Private `__clone()` (prevent cloning)
- [ ] Public static `instance(): static` method using `static::class` as key

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

### 1.4 — `src/Plugin.php`
- [ ] Namespace: `WPHZ\UGC`
- [ ] Declare as `final class Plugin extends AbstractSingleton`
- [ ] Constructor calls `init_hooks()`
- [ ] `init_hooks()` method:
  - [ ] Guard: Check `is_woocommerce_active()` — if false, hook admin notice and return
  - [ ] Initialize `AdminMenu::instance()->init()`
  - [ ] Initialize `ShortcodeRegistrar::instance()->init()`
  - [ ] Initialize `FrontendAssets::instance()->init()`
  - [ ] Initialize `CartHandler::instance()->init()`
  - [ ] Initialize all AJAX handlers: `SaveConfig`, `SaveContent`, `ProductSearch`, `DeleteItem`
- [ ] `is_woocommerce_active()` → checks `class_exists('WooCommerce')`
- [ ] `notice_wc_missing()` → renders admin error notice

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
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', [$this, 'notice_wc_missing']);
            return;
        }

        AdminMenu::instance()->init();
        ShortcodeRegistrar::instance()->init();
        FrontendAssets::instance()->init();
        CartHandler::instance()->init();

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

### 1.5 — `src/Installer/Installer.php`
- [ ] Namespace: `WPHZ\UGC\Installer`
- [ ] `activate()` static method:
  - [ ] Call `create_tables()`
  - [ ] Call `set_default_config()`
  - [ ] Call `flush_rewrite_rules()`
- [ ] `deactivate()` static method:
  - [ ] Call `flush_rewrite_rules()`
- [ ] `create_tables()` private static method:
  - [ ] Create `{prefix}wphz_ugc_items` table using `dbDelta()`
  - [ ] Table columns: `id`, `carousel_id`, `sort_order`, `video_id`, `video_url`, `product_ids`, `created_at`, `updated_at`
  - [ ] Keys: PRIMARY on `id`, INDEX on `carousel_id`, INDEX on `sort_order`
  - [ ] Set `wphz_ugc_db_version` option to `WPHZ_UGC_VERSION`
- [ ] `set_default_config()` private static method:
  - [ ] Set `wphz_ugc_config` with defaults: `heading`, `subheading`, `mute: true`, `direction: 'ltr'`
  - [ ] Set `wphz_ugc_danger_config` with defaults: `on_arrow_right: ''`, `on_arrow_left: ''`
  - [ ] **Only set if option doesn't already exist** (use `if (!get_option(...))`)

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
                'direction'  => 'ltr',
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

---

## Cross-Phase Contract (from this phase)

> **`wphz_ugc_config`** option key and its JSON shape are the single source of truth referenced by Phase 2 (Repository), Phase 3 (ConfigPage), and Phase 5 (ShortcodeRenderer). **Never change this key name without updating all three.**

---

## Verification

- [ ] Plugin activates without PHP errors on WP 6.0+ / PHP 8.0+
- [ ] `wp_wphz_ugc_items` table is created on activation
- [ ] `wphz_ugc_config` option created with defaults
- [ ] `wphz_ugc_danger_config` option created with defaults
- [ ] Plugin admin notice fires when WooCommerce is inactive
- [ ] `composer install` runs clean, `vendor/autoload.php` exists
