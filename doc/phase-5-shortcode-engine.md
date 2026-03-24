# Phase 5 — Shortcode Engine

> **Goal:** Register `[wphz_ugc_carousel]`, parse attributes (falling back to DB defaults), fetch carousel items, and pass everything to the frontend template renderer.  
> **Dependencies:** Phase 2 (both Repositories), Phase 6 (templates)

---

## Implementation Order

```
Phase 1 ✅ → Phase 2 ✅ → Phase 9 ✅ → Phase 3 ✅ → Phase 4 ✅ → [Phase 5] → Phase 6 → Phase 7 → Phase 8
```

---

## Files to Create

| # | File Path | Purpose |
|---|-----------|---------|
| 1 | `src/Shortcode/ShortcodeRegistrar.php` | Registers `[wphz_ugc_carousel]` shortcode |
| 2 | `src/Shortcode/ShortcodeRenderer.php` | Resolves attrs, fetches data, calls template |

---

## Shortcode Attribute Contract

| Attribute | Values | Default Source |
|-----------|--------|----------------|
| `sound` | `"mute"` / `"unmute"` | `wphz_ugc_config.mute` |
| `slide` | `"ltr"` / `"rtl"` | `wphz_ugc_config.direction` |
| `carousel_id` | any string | `"default"` |

> **Rule:** Shortcode attributes OVERRIDE display only. They never write back to saved config.

---

## Checklist

### 5.1 — `src/Shortcode/ShortcodeRegistrar.php`
- [ ] Namespace: `WPHZ\UGC\Shortcode`
- [ ] Extends `AbstractSingleton`
- [ ] `init()` → calls `add_shortcode('wphz_ugc_carousel', [ShortcodeRenderer::instance(), 'render'])`

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

### 5.2 — `src/Shortcode/ShortcodeRenderer.php`
- [ ] Namespace: `WPHZ\UGC\Shortcode`
- [ ] Extends `AbstractSingleton`
- [ ] `render(array $atts): string`:
  - [ ] Get config from `CarouselRepository::instance()->get_config()`
  - [ ] Get danger config from `CarouselRepository::instance()->get_danger_config()`
  - [ ] Merge shortcode atts over defaults using `shortcode_atts()`:
    - `sound` → defaults to `'mute'` or `'unmute'` based on config
    - `slide` → defaults to `$config['direction']` (fallback `'ltr'`)
    - `carousel_id` → defaults to `'default'`
  - [ ] Fetch items via `ItemRepository::instance()->get_all($atts['carousel_id'])`
  - [ ] Hydrate items (decode product_ids + attach WC product objects)
  - [ ] If no items → return empty string (no broken HTML)
  - [ ] Enqueue frontend assets via `Frontend\AssetLoader::instance()->enqueue_now($atts, $danger)`
  - [ ] Return rendered template via `TemplateLoader::render_return('frontend/carousel-wrapper', [...])`
  - [ ] Template data: `heading`, `subheading`, `items`, `sound`, `slide`, `is_muted`
- [ ] `hydrate_items(array $items): array` (private):
  - [ ] For each item: decode `product_ids` JSON
  - [ ] Attach WC product objects via `fetch_products()`
  - [ ] Add `products` key to each item
- [ ] `fetch_products(array $pids): array` (private):
  - [ ] For each product ID: call `wc_get_product()`
  - [ ] Filter: only include products where `$product->is_visible()` returns true
  - [ ] Return array of WC product objects

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

        $atts = shortcode_atts([
            'sound'       => $config['mute'] ? 'mute' : 'unmute',
            'slide'       => $config['direction'] ?? 'ltr',
            'carousel_id' => 'default',
        ], $atts, 'wphz_ugc_carousel');

        $items = ItemRepository::instance()->get_all($atts['carousel_id']);
        $items = $this->hydrate_items($items);

        if (empty($items)) return '';

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

## Verification

- [ ] `[wphz_ugc_carousel]` uses DB defaults when no attrs given
- [ ] `sound="unmute"` overrides mute default without changing saved config
- [ ] `slide="rtl"` overrides direction without changing saved config
- [ ] Empty carousel (no items) returns empty string (no broken HTML)
- [ ] Frontend assets are only enqueued when shortcode is present on page
- [ ] Product hydration filters out invisible/non-existent products
- [ ] Multiple shortcodes on same page: assets only enqueued once
