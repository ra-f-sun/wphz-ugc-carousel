# WordPress Plugin Development Guide

Based on patterns from `ugc-carousels-for-woo`.

---

## 1. File & Directory Structure

Every PHP file that could be accessed directly must begin with an ABSPATH guard as its very first executable line after the opening tag and docblock:

```php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

Class files must use PSR-4 naming — one class per file, filename matches class name exactly (`ParseCsv.php` contains `class ParseCsv`). Directory structure mirrors namespace segments:

```
src/
  Ajax/           → namespace Plugin\Ajax
  Repository/     → namespace Plugin\Repository
  Helpers/        → namespace Plugin\Helpers
  Admin/          → namespace Plugin\Admin
  Frontend/       → namespace Plugin\Frontend
  Shortcode/      → namespace Plugin\Shortcode
  Installer/      → namespace Plugin\Installer
assets/
  admin/
  frontend/
templates/
  admin/
  frontend/
```

---

## 2. Naming Conventions

### PHP

| Element | Convention | Example |
|---|---|---|
| Namespace | `VendorPrefix\PluginName\SubNamespace` | `WPHZ\UGCCarousels\Ajax` |
| Class | PascalCase | `ParseCsv`, `ItemRepository` |
| Method | snake_case | `handle()`, `get_all()`, `delete_by_carousel()` |
| Variable | snake_case | `$carousel_id`, `$product_ids`, `$hide_atc` |
| Constant | SCREAMING_SNAKE_CASE | `WPHZ_UGC_VERSION`, `ABSPATH` |
| Hook/action name | plugin-slug prefix + snake_case | `ugcc_parse_csv`, `ugcc_admin` |
| DB table | `{$wpdb->prefix}plugin_slug_purpose` | `wphz_ugc_items` |
| Option name | plugin-slug prefix | `wphz_ugc_db_version` |

Never use generic names that could collide globally. Every hook, option, and table name must carry the plugin prefix.

### JavaScript

| Element | Convention | Example |
|---|---|---|
| Function | camelCase with plugin prefix | `ugccConfirmImport()`, `onImportFileChange()` |
| Variable (module-level) | camelCase with plugin prefix | `ugccModalRows`, `newItemRowId` |
| Class | PascalCase | `UGCCCarousel` |
| Method | camelCase | `_playCenter()` |
| Private method | camelCase with leading underscore | `_applyTransform()`, `_buildInfiniteTrack()` |
| Event handler | `on` prefix describing the event | `onImportFileChange`, `onConfigFormSubmit` |
| Data attribute | kebab-case | `data-carousel-id`, `data-hide-atc`, `data-src-hd` |

Module-level variables and functions carry the plugin prefix to avoid collisions with other scripts on the page. Local variables inside functions do not need the prefix.

Single-letter variable names are never acceptable except as loop counters (`i`, `j`) in tight numeric loops:

```javascript
// Wrong
const hd = video.dataset.srcHd;
const v  = slide.querySelector(".ugcc-video");
const c  = document.createElement("canvas");

// Correct
const hdVideoUrl   = video.dataset.srcHd;
const videoElement = slide.querySelector(".ugcc-video");
const canvas       = document.createElement("canvas");
```

### CSS

All classes carry the plugin prefix using BEM-inspired structure:

```
ugcc-[block]              .ugcc-carousel, .ugcc-slide, .ugcc-video
ugcc-[block]--[modifier]  .ugcc-slide--active, .ugcc-products--carousel
ugcc-[utility]-[name]     .ugcc-badge--warn, .ugcc-badge--info
```

Never use unprefixed classes for plugin-specific elements. Never use inline styles for anything that could be expressed as a class toggle.

---

## 3. Security

### Every AJAX handler must follow this exact pattern

```php
public function handle(): void {
    // 1. Read nonce using filter_input — never direct $_POST/$_GET
    $nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS )
        ?? filter_input( INPUT_POST, 'plugin_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS )
        ?? '';

    // 2. Verify nonce AND capability together — both must pass
    if ( ! wp_verify_nonce( $nonce, 'plugin_admin' ) || ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'plugin-slug' ) ), 403 );
        return;
    }

    // 3. Validate and cast all inputs
    $carousel_id = (int) filter_input( INPUT_POST, 'carousel_id', FILTER_VALIDATE_INT );
    if ( $carousel_id <= 0 ) {
        wp_send_json_error( array( 'message' => __( 'Invalid ID.', 'plugin-slug' ) ) );
        return;
    }

    // ... handler logic ...
}
```

Key rules:

- **Never** use `$_POST`, `$_GET`, or `$_REQUEST` directly. Always use `filter_input(INPUT_POST, ...)` or `filter_input(INPUT_GET, ...)`.
- **Never** mix POST and GET via `$_REQUEST` — be explicit about which superglobal you expect.
- **Always** verify nonce and capability on every single request, including Phase 2 of a multi-phase flow. Do not trust that a prior phase already verified.
- Admin-only AJAX actions must use `wp_ajax_` hook only — never `wp_ajax_nopriv_`. Unauthenticated endpoints are a separate deliberate decision, not a default.

### Input validation by type

```php
// Integer
$id = (int) filter_input( INPUT_POST, 'id', FILTER_VALIDATE_INT );
if ( $id <= 0 ) { /* reject */ }

// String (safe characters only)
$name = filter_input( INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ?? '';

// URL
$url = esc_url_raw( filter_input( INPUT_POST, 'url', FILTER_SANITIZE_URL ) ?? '' );

// JSON body field
$raw   = filter_input( INPUT_POST, 'items', FILTER_DEFAULT );
$items = json_decode( wp_unslash( (string) $raw ), true );
if ( ! is_array( $items ) ) { /* reject */ }
// wp_unslash() is required because WordPress adds magic slashes to all POST data
```

### File uploads

```php
$tmp_name = isset( $_FILES['csv_file']['tmp_name'] ) ? (string) $_FILES['csv_file']['tmp_name'] : '';

// is_uploaded_file() prevents path traversal attacks
if ( ! $tmp_name || ! is_uploaded_file( $tmp_name ) ) { /* reject */ }

// Always check extension
$file_name = sanitize_file_name( (string) $_FILES['csv_file']['name'] );
$extension = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
if ( 'csv' !== $extension ) { /* reject */ }

// Always check MIME type (belt AND suspenders with extension)
$uploaded_type      = isset( $_FILES['csv_file']['type'] ) ? (string) $_FILES['csv_file']['type'] : '';
$allowed_mime_types = array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' );
if ( ! in_array( $uploaded_type, $allowed_mime_types, true ) ) { /* reject */ }
```

### Output escaping

Escape at the point of output, not at the point of save. Use the most specific function for the context:

```php
echo esc_html( $name );        // plain text in HTML context
echo esc_attr( $value );       // inside HTML attributes
echo esc_url( $url );          // inside href/src attributes
echo esc_url_raw( $url );      // for DB save or HTTP redirect (not HTML output)
echo wp_kses_post( $html );    // trusted rich HTML (allows basic tags)
echo wp_json_encode( $array ); // JSON output — never json_encode()
```

In JavaScript, never concatenate server-provided strings into HTML without escaping:

```javascript
// Wrong — XSS risk
html += '<span>' + product.name + '</span>';

// Correct — jQuery text-node trick
function ugccEscHtml( str ) {
    return $('<div>').text(String(str)).html();
}
html += '<span>' + ugccEscHtml(product.name) + '</span>';

// For attribute values
function ugccEscAttr( val ) {
    return String(val == null ? '' : val)
        .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
html += '<input value="' + ugccEscAttr(id) + '">';
```

### Database queries

All queries that include variables must use `$wpdb->prepare()`. No exceptions.

```php
// Wrong — SQL injection risk
$wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_value = '$sku'" );

// Correct
$wpdb->get_col(
    $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpdb->postmeta is a WP core table reference, not user input.
        "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s",
        $sku
    )
);
```

`{$wpdb->postmeta}` is safe because it is a WP core table reference, not user input. PHPCS will still flag `InterpolatedNotPrepared` — suppress with a comment explaining why.

### Type casting pitfalls

MySQL returns tinyint columns as strings. `(bool) '0'` is `true` in PHP because `'0'` is a non-empty string. Always double-cast:

```php
// Wrong — '0' cast to bool is true
$show_products = (bool) ( $config['show_products'] ?? 1 );

// Correct — cast to int first, then bool
$show_products = (bool) (int) ( $config['show_products'] ?? 1 );
```

---

## 4. Architecture Rules

### One class, one responsibility

Each class does exactly one thing. AJAX handlers only orchestrate: validate input, call a repository or service, return JSON. They do not contain SQL. Repositories only contain SQL. Templates only contain output.

```
AJAX handler → validate + authorize + call repository → return JSON
Repository   → SQL only (INSERT, SELECT, UPDATE, DELETE)
Template     → output only (echo, esc_html, etc.)
Helper       → stateless utility functions
```

### No direct `$wpdb` outside repositories

AJAX handlers must never call `$wpdb->insert()`, `$wpdb->delete()`, or `$wpdb->update()` directly. Add a method to the appropriate repository:

```php
// Wrong — in an AJAX handler
global $wpdb;
$wpdb->delete( $wpdb->prefix . 'plugin_items', array( 'carousel_id' => $carousel_id ) );

// Correct — delegate to repository
ItemRepository::instance()->delete_by_carousel( $carousel_id );
```

### Safe database migrations

Never run `ALTER TABLE` blindly. Always check first:

```php
$table = $wpdb->prefix . 'plugin_items';
if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'new_column'" ) ) {
    $wpdb->query( "ALTER TABLE {$table} ADD COLUMN new_column tinyint(1) DEFAULT 1" );
}
```

### Singleton pattern for service classes

Use `AbstractSingleton` for AJAX handlers, repositories, and service classes so hooks are only registered once:

```php
class ParseCsv extends AbstractSingleton {
    public function init(): void {
        add_action( 'wp_ajax_plugin_parse_csv', array( $this, 'handle' ) );
    }
}

// In Plugin.php init_hooks():
ParseCsv::instance()->init();
```

---

## 5. Comment Style

### The rule: comment the WHY, never the WHAT

Code that needs a comment explaining what it does is a signal to rename or refactor. Comments exist only for:

- A hidden constraint or invariant not obvious from the code
- A workaround for a specific browser/platform/framework bug
- A non-obvious business rule
- An explanation of why a "wrong-looking" approach is intentionally correct

```php
// Wrong — explains what, not why
// Loop through products
foreach ( $products as $product ) { ... }

// Correct — explains a non-obvious constraint
// wp_add_inline_style() cannot be used here because shortcodes render
// during the_content(), which fires after wp_head() has already run.
$output = sprintf( '<style id="plugin-css-%d">%s</style>', $id, $raw_css );
```

```javascript
// Wrong
// Set muted to true
video.muted = true;

// Correct — explains the guard pattern and why it exists
// shouldPlay guard prevents a stale canplay listener from revealing
// the overlay after resetToPoster() has already hidden the video.
const revealVideo = () => {
    if (video.dataset.shouldPlay !== "true") return;
    video.classList.add("ugcc-video--revealed");
};
```

Never write comments that reference the task, fix, or callers:

- `// Added for the show_products feature` — belongs in the PR description
- `// Used by ConfirmImport` — rots as callers change
- `// Fixed in issue #123` — belongs in git log
- `// TODO: remove when WC upgrades` — put in an issue tracker, not source

### PHP PHPDoc — required on every public method

```php
/**
 * One-line summary of what this method does.
 *
 * Longer explanation only if the method has non-obvious behavior.
 *
 * Expects POST fields:
 *  - nonce       string  WordPress nonce for 'plugin_admin'.
 *  - carousel_id int     Target carousel ID.
 *  - items       string  JSON-encoded array of confirmed rows.
 *
 * @since  1.0.0
 * @param  int   $carousel_id The carousel to act on.
 * @param  array $data        Validated input data.
 * @return array<string, mixed>
 */
public function my_method( int $carousel_id, array $data ): array {
```

Required tags on every public method: `@since`, `@param` (one per parameter with type + name + description), `@return` (with type).

Every file must have a file-level docblock with `@package`:

```php
<?php
/**
 * Short description of this file's purpose.
 *
 * @package VendorPrefix\PluginName
 */
```

### JavaScript JSDoc — required on every named function and class method

```javascript
/**
 * Move the carousel to the given slide index.
 *
 * Re-entrant calls are queued and replayed after the current shift completes.
 *
 * @param {number}  index   - Target slide index (0-based, within original items).
 * @param {boolean} animate - Whether to apply CSS transition.
 * @return {void}
 */
_applyTransform( index, animate ) { ... }
```

Anonymous functions passed as callbacks do not need JSDoc. Named functions defined at module level do.

---

## 6. WPCS / PHPCS Compliance

### Run before every commit

```bash
composer run phpcs         # check
composer run phpcbf        # auto-fix formatting
```

### Common suppressions — always add a reason

When a rule must be suppressed, the comment must explain why:

```php
// File-level suppression with reason
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct postmeta query required to find ALL products matching a SKU. wc_get_product_id_by_sku() returns only one result. NoCaching intentional: one-time admin import action.

// Single-line suppression with reason
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpdb->postmeta is a WP core table reference, not user input.

// $_FILES suppression with reason
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES['csv_file']['tmp_name'] validated via is_uploaded_file() and MIME/extension checks.

// Template variable suppression with reason
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are injected by TemplateLoader::render() inside a static method. PHP scopes the included file to that function's stack frame; these variables never enter global scope.

// Raw output stream suppression with reason
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download stream must remain raw.
```

### Specific rules to know

**Use `wp_json_encode()` not `json_encode()`:**

```php
echo wp_json_encode( $data );
```

**Use `gmdate()` not `date()`:**

```php
$filename = 'export-' . gmdate( 'Y-m-d' ) . '.csv';
```

**No short ternary — use full ternary:**

```php
// Wrong — triggers DisallowShortTernary
$variation = $attrs ?: null;

// Correct
$variation = ! empty( $attrs ) ? $attrs : null;
```

**Clear output buffer before file download headers:**

```php
while ( ob_get_level() ) {
    ob_end_clean();
}
header( 'Content-Type: text/csv; charset=utf-8' );
header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
header( 'Pragma: no-cache' );
header( 'Expires: 0' );
```

**Use `FILTER_SANITIZE_FULL_SPECIAL_CHARS` — `FILTER_SANITIZE_STRING` is deprecated:**

```php
// Wrong — deprecated
$value = filter_input( INPUT_POST, 'field', FILTER_SANITIZE_STRING );

// Correct
$value = filter_input( INPUT_POST, 'field', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
```

**`@since` tags are required on every public method.** WPCS enforces this. The value is the plugin version when the method was introduced.

### Plugin Check (wordpress.org submission)

Suppressions in `phpcs.xml.dist` do NOT apply to Plugin Check. Only inline `// phpcs:ignore` and `// phpcs:disable` comments work. Key things Plugin Check flags:

- Direct database calls without caching (suppress with reason comment)
- Missing `@package` tags on files
- Unprefixed global functions, hooks, options, and table names
- Unescaped output in `echo` statements

---

## 7. Multi-Phase Operations Pattern

When an operation requires user verification before committing (imports, bulk actions, significant deletions), always use a two-phase design:

**Phase 1 — Read only, return findings:**

- Validate input and authorization
- Query everything needed to show the user what will happen
- Return structured JSON to the frontend
- Write nothing to the database

**Phase 2 — Write only confirmed selections:**

- Re-validate nonce and capability independently — do not trust Phase 1's prior validation
- Accept only the user-confirmed subset of data
- Sanitize and validate all inputs again server-side
- Write to the database through the repository

This pattern prevents blind imports, catches ambiguous data (shared SKUs across variations), and surfaces warnings (hidden or draft products) before any data is changed.

---

## 8. JavaScript Architecture

### Module pattern with jQuery

Wrap all admin JavaScript in a jQuery document-ready callback to avoid global scope pollution:

```javascript
(function($) {
    'use strict';

    // All code here.
    // ugcc-prefixed functions for module-level items.
    // Local variables inside functions stay local — no prefix needed.

})(jQuery);
```

### Named functions over anonymous callbacks

```javascript
// Wrong — anonymous, untraceable in stack traces
$('#button').on('click', function(e) { ... });

// Correct — named, debuggable, extractable
function onConfigFormSubmit(e) { ... }
$('#config-form').on('submit', onConfigFormSubmit);
```

### State between phases

When a multi-step UI flow requires state between phases, store it in a named module-level variable with a clear comment:

```javascript
/** Parsed rows stored so the confirm handler can reference them in Phase 2. */
var ugccModalRows = [];
```

### No unsanitized DOM injection

Any string from the server that touches `innerHTML`, `.html()`, or string concatenation into HTML must pass through an escaping function first. For attributes that accept URLs, assign via DOM property (`element.src = url`) — the browser enforces src safety on property assignment.
