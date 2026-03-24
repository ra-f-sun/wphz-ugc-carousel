# Phase 2 — Data Layer (Repository + Transient)

> **Goal:** Clean read/write API over DB and options. No AJAX, no UI. Other phases call these classes only.  
> **Rule:** Repositories are the ONLY classes that touch `$wpdb` or `get_option`/`update_option`.  
> **Dependencies:** Phase 1 (plugin boots, DB table exists)

---

## Implementation Order

```
Phase 1 ✅ → [Phase 2] → Phase 9 → Phase 3 → Phase 4 → Phase 5 → Phase 6 → Phase 7 → Phase 8
```

---

## Files to Create

| # | File Path | Purpose |
|---|-----------|---------|
| 1 | `src/Repository/CarouselRepository.php` | CRUD for carousel config (`wp_options`) |
| 2 | `src/Repository/ItemRepository.php` | CRUD for carousel items (custom table `wp_wphz_ugc_items`) |
| 3 | `src/Helpers/TransientHelper.php` | get/set/flush transient wrappers for product search cache |

---

## Checklist

### 2.1 — `src/Repository/CarouselRepository.php`
- [ ] Namespace: `WPHZ\UGC\Repository`
- [ ] Extends `AbstractSingleton`
- [ ] Define private const `CONFIG_KEY = 'wphz_ugc_config'`
- [ ] Define private const `DANGER_KEY = 'wphz_ugc_danger_config'`
- [ ] `get_config(): array` — reads `CONFIG_KEY` option, JSON-decodes, returns array
- [ ] `save_config(array $data): bool` — JSON-encodes and saves to `CONFIG_KEY`
- [ ] `get_danger_config(): array` — reads `DANGER_KEY` option, JSON-decodes, returns array
- [ ] `save_danger_config(array $data): bool` — JSON-encodes and saves to `DANGER_KEY`

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

### 2.2 — `src/Repository/ItemRepository.php`
- [ ] Namespace: `WPHZ\UGC\Repository`
- [ ] Extends `AbstractSingleton`
- [ ] Private `table(): string` — returns `$wpdb->prefix . 'wphz_ugc_items'`
- [ ] `get_all(string $carousel_id = 'default'): array`
  - [ ] Use `$wpdb->prepare()` with `SELECT * ... WHERE carousel_id = %s ORDER BY sort_order ASC`
  - [ ] Return empty array if no results
- [ ] `get_by_id(int $id): ?array`
  - [ ] Use `$wpdb->get_row()` with `$wpdb->prepare()`
  - [ ] Return `null` if not found
- [ ] `insert(array $data): int|false`
  - [ ] Use `$wpdb->insert()` with sanitized fields
  - [ ] `carousel_id` defaults to `'default'`
  - [ ] `sort_order` cast to int
  - [ ] `video_id` cast to int
  - [ ] `video_url` sanitized with `esc_url_raw()`
  - [ ] `product_ids` JSON-encoded from int array (`array_map('intval', ...)`)
  - [ ] Return `$wpdb->insert_id` on success, `false` on failure
- [ ] `update(int $id, array $data): bool`
  - [ ] Only update fields present in `$data`
  - [ ] Sanitize `sort_order`, `product_ids`, `video_url` if present
  - [ ] Return false if no fields to update
- [ ] `delete(int $id): bool` — use `$wpdb->delete()`
- [ ] `reorder(array $order): void` — bulk-update `sort_order`, `$order = [item_id => new_sort_order]`

```php
<?php
namespace WPHZ\UGC\Repository;

use WPHZ\UGC\AbstractSingleton;

class ItemRepository extends AbstractSingleton {

    private function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'wphz_ugc_items';
    }

    public function get_all(string $carousel_id = 'default'): array {
        global $wpdb;
        $table = $this->table();
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

    public function reorder(array $order): void {
        foreach ($order as $id => $sort) {
            $this->update((int) $id, ['sort_order' => (int) $sort]);
        }
    }
}
```

### 2.3 — `src/Helpers/TransientHelper.php`
- [ ] Namespace: `WPHZ\UGC\Helpers`
- [ ] Static class (no singleton needed)
- [ ] Define `PREFIX = 'wphz_ugc_'`
- [ ] Define `PRODUCT_TTL = 300` (5 minutes)
- [ ] `product_key(string $search_term): string` — returns `PREFIX . 'product_' . md5(strtolower(trim($search_term)))`
- [ ] `get(string $key): mixed` — wraps `get_transient()`, returns `null` instead of `false` on miss
- [ ] `set(string $key, mixed $value, int $expiry): void` — wraps `set_transient()`
- [ ] `flush_products(): void`:
  - [ ] Call `wc_delete_product_transients()` (WC helper)
  - [ ] Delete own transients from `wp_options` with LIKE pattern `_transient_{PREFIX}product_%`

```php
<?php
namespace WPHZ\UGC\Helpers;

class TransientHelper {

    private const PREFIX      = 'wphz_ugc_';
    private const PRODUCT_TTL = 300;

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
        wc_delete_product_transients();
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

---

## Cross-Phase Contract

> `TransientHelper::product_key()` and `TransientHelper::flush_products()` are called exclusively by `Ajax/ProductSearch.php` (Phase 9). Any change to the key pattern must be reflected there.

> `CarouselRepository` constants `CONFIG_KEY` and `DANGER_KEY` must match what Phase 1 `Installer` writes and what Phase 3/5/9 read.

---

## Verification

- [ ] `ItemRepository::insert()` → `get_all()` round-trip preserves `product_ids` JSON
- [ ] `ItemRepository::get_by_id()` returns `null` for non-existent ID
- [ ] `ItemRepository::delete()` returns `true` for existing item
- [ ] `ItemRepository::reorder()` updates sort_order correctly
- [ ] `CarouselRepository::get_config()` returns array matching defaults from Phase 1
- [ ] `CarouselRepository::save_config()` → `get_config()` round-trip works
- [ ] `TransientHelper::get()` returns `null` (not `false`) on cache miss
- [ ] `TransientHelper::set()` → `get()` retrieves cached value
- [ ] `TransientHelper::flush_products()` clears WC transients + plugin transients
