# Phase 4 — Admin UI: Content Manager (Videos + Products)

> **Goal:** Admin can add video line items, attach products, drag to reorder, and delete items. On save, items are persisted to DB and a shortcode is displayed.  
> **Dependencies:** Phase 2 (ItemRepository), Phase 1 (AssetLoader with `wp_enqueue_media`)

---

## Implementation Order

```
Phase 1 ✅ → Phase 2 ✅ → Phase 9 ✅ → Phase 3 ✅ → [Phase 4] → Phase 5 → Phase 6 → Phase 7 → Phase 8
```

---

## Files to Create

| # | File Path | Purpose |
|---|-----------|---------|
| 1 | `src/Admin/ContentPage.php` | Content tab data provider |
| 2 | `templates/admin/content-tab.php` | Content manager HTML |
| 3 | `templates/admin/item-row.php` | Single video line item row HTML |
| 4 | Update `assets/admin/admin.js` | Add content manager JS behaviors |
| 5 | Update `assets/admin/admin.css` | Admin styling |

---

## Checklist

### 4.1 — `src/Admin/ContentPage.php`
- [ ] Namespace: `WPHZ\UGC\Admin`
- [ ] Extends `AbstractSingleton`
- [ ] `get_data(): array`:
  - [ ] Fetch items via `ItemRepository::instance()->get_all('default')`
  - [ ] Decode `product_ids` JSON for each item using `json_decode($item['product_ids'], true) ?: []`
  - [ ] Return array with keys: `items` (decoded items), `shortcode` (generated shortcode string)
- [ ] `build_shortcode(): string` (private):
  - [ ] Read config from `CarouselRepository::instance()->get_config()`
  - [ ] Build: `[wphz_ugc_carousel sound="{mute|unmute}" slide="{ltr|rtl}"]`

```php
<?php
namespace WPHZ\UGC\Admin;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Repository\ItemRepository;

class ContentPage extends AbstractSingleton {

    public function get_data(): array {
        $items = ItemRepository::instance()->get_all('default');

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

### 4.2 — `templates/admin/content-tab.php`
- [ ] ABSPATH guard
- [ ] Outer wrapper: `<div class="wphz-content-manager">`
- [ ] **Shortcode display box**:
  - [ ] Label: "Your Shortcode:"
  - [ ] `<code id="wphz-shortcode">` showing the generated shortcode
  - [ ] Copy button: `<button id="wphz-copy-shortcode">`
- [ ] **Add video button**: `<button id="wphz-add-item">+ Add Video from Media Library</button>`
- [ ] **Items list**: `<ul id="wphz-items-list" class="wphz-items-list">` (sortable via jquery-ui-sortable)
  - [ ] Loop through `$items` and render each via `TemplateLoader::render('admin/item-row', ['item' => $item])`
- [ ] **Save button**: `<button id="wphz-save-content">Save & Get Shortcode</button>`
- [ ] **Status span**: `<span class="wphz-save-status"></span>`

### 4.3 — `templates/admin/item-row.php`
Each item row must contain:
- [ ] `<li class="wphz-item-row" data-id="{row_id}">`
- [ ] **Drag handle**: `<span class="dashicons dashicons-move wphz-drag-handle"></span>`
- [ ] **Video preview section** (`<div class="wphz-item-preview">`):
  - [ ] If video URL exists: `<video>` element with `width="120"`, `muted`, `playsinline`, `preload="metadata"`
  - [ ] If no video: "No video selected" placeholder text
  - [ ] Hidden inputs: `items[{row_id}][video_id]` and `items[{row_id}][video_url]`
- [ ] **Product search section** (`<div class="wphz-item-products">`):
  - [ ] Label: "Attached Products"
  - [ ] Search input: `<input type="text" class="wphz-product-search" data-row="{row_id}">`
  - [ ] Suggestions dropdown: `<ul class="wphz-product-suggestions"></ul>`
  - [ ] Selected products chips: `<ul class="wphz-selected-products">`
    - [ ] For each product ID: render chip with product name, hidden input, and remove (×) button
    - [ ] Use `wc_get_product($pid)` to get product name
    - [ ] Hidden input: `items[{row_id}][product_ids][]`
- [ ] **Delete button**: `<button class="wphz-delete-item" data-id="{row_id}">Remove</button>`

```php
<?php defined('ABSPATH') || exit;
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
        <div class="wphz-product-search-wrap">
            <input type="text" class="wphz-product-search"
                   placeholder="<?php esc_attr_e('Search products...', 'wphz-ugc'); ?>"
                   data-row="<?php echo esc_attr($row_id); ?>">
            <ul class="wphz-product-suggestions"></ul>
        </div>
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

### 4.4 — Admin JS: Content Manager Behaviors (add to `assets/admin/admin.js`)

The JS must implement:

- [ ] **Add Video button** (`#wphz-add-item`):
  - [ ] Opens WP media picker (filters to video only)
  - [ ] On select: creates a new `item-row` LI element with video preview + hidden inputs
  - [ ] Appends to `#wphz-items-list`
- [ ] **Product search** (`.wphz-product-search` keyup, debounced 300ms):
  - [ ] Fires `wphz_ugc_product_search` AJAX action (Phase 9)
  - [ ] Results render as dropdown `<ul>` with product name + thumbnail
  - [ ] Clicking result adds chip to `.wphz-selected-products` with hidden `<input>`
- [ ] **Remove product** (`.wphz-remove-product` click):
  - [ ] Removes the product chip `<li>` from selected list
- [ ] **Delete row** (`.wphz-delete-item` click):
  - [ ] If row has existing DB ID: fires `wphz_ugc_delete_item` AJAX action
  - [ ] Removes the `<li>` from DOM
- [ ] **Drag-reorder** (jquery-ui-sortable on `#wphz-items-list`):
  - [ ] Handle: `.wphz-drag-handle`
  - [ ] Reorder updates sort position on save
- [ ] **Save content** (`#wphz-save-content` click):
  - [ ] Collects all item rows' data (video_id, video_url, product_ids)
  - [ ] Fires `wphz_ugc_save_content` AJAX action
  - [ ] On success: updates the shortcode display
- [ ] **Copy shortcode** (`#wphz-copy-shortcode` click):
  - [ ] Copies shortcode text to clipboard

---

## Product Search Strategy

- Admin JS listens to `keyup` on `.wphz-product-search` (debounced 300ms)
- Fires `wphz_ugc_product_search` AJAX action (Phase 9)
- Results render as dropdown `<ul>` with product name + thumbnail
- Clicking a result adds a chip to `.wphz-selected-products` with a hidden `<input>`
- WooCommerce's `wc_get_product()` is used server-side
- Transients cache search results for 5 min (Phase 2, `TransientHelper`)

---

## Verification

- [ ] Media picker opens and returns video attachment ID + URL
- [ ] Video preview thumbnail renders in item row
- [ ] Product search autocomplete fires after 2 chars (debounced)
- [ ] Selecting a product adds chip with hidden input
- [ ] Removing a product chip works
- [ ] Drag-reorder updates `sort_order` on save
- [ ] Delete item removes row from DB and DOM
- [ ] Save persists all items correctly (idempotent — saving twice doesn't duplicate)
- [ ] Shortcode is correctly generated and copyable
- [ ] Adding new rows with no video selected shows placeholder
