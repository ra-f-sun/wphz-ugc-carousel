# Cross-Phase Contracts & Testing

> **This document is CRITICAL.** Mismatches here cause silent bugs.

---

## Implementation Order (Strict)

```
Phase 1 → Phase 2 → Phase 9 → Phase 3 → Phase 4 → Phase 5 → Phase 6 → Phase 7 → Phase 8
```

Each phase can be tested in isolation. **Phase 2 must complete before Phase 3, 4, 5, and 9** since all delegate persistence to Repository classes.

---

## C1: Config Option Key Contract

| Key | `wphz_ugc_config` |
|-----|-------------------|
| **Shape** | `{ heading, subheading, mute(bool), direction('ltr'\|'rtl') }` |
| **Writers** | `Installer::set_default_config()` (Phase 1), `Ajax/SaveConfig` (Phase 9) |
| **Readers** | `CarouselRepository::get_config()` (Phase 2), `ShortcodeRenderer::render()` (Phase 5), `ContentPage::build_shortcode()` (Phase 4) |
| **Rule** | Never read this option directly with `get_option()` outside `CarouselRepository` |

## C2: DB Table Column Contract

| Table | `wp_wphz_ugc_items` |
|-------|---------------------|
| **product_ids** | JSON string, **never** comma-separated |
| **All decoders** | Use `json_decode($item['product_ids'], true) ?: []` |
| **Writers** | `ItemRepository::insert()`, `ItemRepository::update()` |
| **Readers** | `ItemRepository::get_all()` → `ShortcodeRenderer::hydrate_items()` → templates |

## C3: CSS Class / Data-Attr Contract (Phase 6 → Phase 7)

Phase 7 JS is coupled **only** to HTML classes in Phase 6's contract table. If Phase 6 renames a class, Phase 7 must update.

## C4: `wp_localize_script` Data Contract (Phase 5/7 → JS)

`Frontend/AssetLoader::enqueue_now()` must pass this exact object:

```php
wp_localize_script('wphz-ugc-frontend', 'wphzUGCFrontend', [
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce'   => wp_create_nonce('wphz_ugc_atc'),
    'danger'  => $danger, // ['on_arrow_right' => '...', 'on_arrow_left' => '...']
    'i18n'    => [
        'added' => __('Added!', 'wphz-ugc'),
        'error' => __('Error — retry.', 'wphz-ugc'),
    ],
]);
```

JS reads: `window.wphzUGCFrontend.danger`, `window.wphzUGCFrontend.ajaxurl`, etc.

## C5: AJAX Action Name Contract

| PHP `add_action` | JS `action:` field | Handler Class |
|---|---|---|
| `wphz_ugc_save_config` | `wphz_ugc_save_config` | `Ajax/SaveConfig` |
| `wphz_ugc_save_content` | `wphz_ugc_save_content` | `Ajax/SaveContent` |
| `wphz_ugc_product_search` | `wphz_ugc_product_search` | `Ajax/ProductSearch` |
| `wphz_ugc_delete_item` | `wphz_ugc_delete_item` | `Ajax/DeleteItem` |
| `wphz_ugc_add_to_cart` | `wphz_ugc_add_to_cart` | `Frontend/CartHandler` |

## C6: Nonce Action String Contract

| Nonce Action | Where Created | Where Verified |
|---|---|---|
| `wphz_ugc_admin` | `Admin/AssetLoader::enqueue()` via `wp_localize_script` | All `Ajax/Save*` and `Ajax/Delete*` handlers |
| `wphz_ugc_atc` | `templates/frontend/product-item.php` per button | `Frontend/CartHandler::handle()` |

---

## Frontend AssetLoader (`src/Frontend/AssetLoader.php`)

This file spans Phases 5–8. Create it when implementing Phase 5.

- [ ] Lazy-enqueues assets only when shortcode is rendered
- [ ] `enqueue_now(array $atts, array $danger): void` — called by `ShortcodeRenderer`
- [ ] Guards against double-enqueue with `$enqueued` flag
- [ ] Enqueues `carousel.css` (depends on `woocommerce-general`)
- [ ] Enqueues `carousel.js` (no jQuery dependency, in footer)
- [ ] Localizes `wphzUGCFrontend` per C4 contract above

---

## Complete File Inventory

```
wphz-ugc-carousel/
├── composer.json                         ← Phase 1
├── wphz-ugc-carousel.php                 ← Phase 1
├── src/
│   ├── AbstractSingleton.php             ← Phase 1
│   ├── Plugin.php                        ← Phase 1
│   ├── Admin/
│   │   ├── AdminMenu.php                 ← Phase 3
│   │   ├── AssetLoader.php               ← Phase 3
│   │   ├── ConfigPage.php                ← Phase 3
│   │   └── ContentPage.php               ← Phase 4
│   ├── Ajax/
│   │   ├── SaveConfig.php                ← Phase 9
│   │   ├── SaveContent.php               ← Phase 9
│   │   ├── ProductSearch.php             ← Phase 9
│   │   └── DeleteItem.php                ← Phase 9
│   ├── Repository/
│   │   ├── CarouselRepository.php        ← Phase 2
│   │   └── ItemRepository.php            ← Phase 2
│   ├── Shortcode/
│   │   ├── ShortcodeRegistrar.php        ← Phase 5
│   │   └── ShortcodeRenderer.php         ← Phase 5
│   ├── Frontend/
│   │   ├── AssetLoader.php               ← Phase 5
│   │   └── CartHandler.php               ← Phase 8
│   ├── Helpers/
│   │   ├── NonceHelper.php               ← Phase 9
│   │   ├── SanitizeHelper.php            ← Phase 9
│   │   ├── TransientHelper.php           ← Phase 2
│   │   └── TemplateLoader.php            ← Phase 3
│   └── Installer/
│       └── Installer.php                 ← Phase 1
├── templates/
│   ├── admin/
│   │   ├── layout.php                    ← Phase 3
│   │   ├── config-tab.php                ← Phase 3
│   │   ├── content-tab.php               ← Phase 4
│   │   └── item-row.php                  ← Phase 4
│   └── frontend/
│       ├── carousel-wrapper.php          ← Phase 6
│       ├── carousel-item.php             ← Phase 6
│       └── product-item.php              ← Phase 6
└── assets/
    ├── admin/
    │   ├── admin.css                     ← Phase 3
    │   └── admin.js                      ← Phase 3/4
    └── frontend/
        ├── carousel.css                  ← Phase 6
        └── carousel.js                   ← Phase 7/8
```
