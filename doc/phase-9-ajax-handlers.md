# Phase 9 — AJAX Handlers

> **Goal:** Four AJAX endpoints, each handling EXACTLY one action. Sanitized via `SanitizeHelper`, nonce via `NonceHelper`.  
> **Dependencies:** Phase 2 (Repositories + TransientHelper), Phase 1 (Plugin boots)

---

## Implementation Order

```
Phase 1 ✅ → Phase 2 ✅ → [Phase 9] → Phase 3 → Phase 4 → Phase 5 → Phase 6 → Phase 7 → Phase 8
```

---

## Files to Create

| # | File Path | Purpose |
|---|-----------|---------|
| 1 | `src/Helpers/NonceHelper.php` | Nonce verification |
| 2 | `src/Helpers/SanitizeHelper.php` | Input sanitization |
| 3 | `src/Ajax/SaveConfig.php` | Save configuration |
| 4 | `src/Ajax/SaveContent.php` | Save carousel items |
| 5 | `src/Ajax/ProductSearch.php` | Search products (cached) |
| 6 | `src/Ajax/DeleteItem.php` | Delete a line item |

---

## Checklist

### 9.1 — NonceHelper
- [ ] `verify(string $action): void` — reads nonce from `$_REQUEST['nonce']` or `$_REQUEST['wphz_nonce']`, calls `wp_verify_nonce()`, sends 403 on failure

### 9.2 — SanitizeHelper
- [ ] `text()` — wraps `sanitize_text_field()`
- [ ] `js_identifier()` — regex `/[^a-zA-Z0-9_\-]/` strip (prevents XSS in danger zone)
- [ ] `url()` — wraps `esc_url_raw()`
- [ ] `int_array()` — `array_map('intval', ...)`

### 9.3 — SaveConfig (`wp_ajax_wphz_ugc_save_config`)
- [ ] Verify nonce `wphz_ugc_admin`
- [ ] Build config: `heading`, `subheading` (sanitize text), `mute` (bool), `direction` (validate ltr/rtl)
- [ ] Build danger: `on_arrow_right`, `on_arrow_left` (js_identifier sanitize)
- [ ] Save via `CarouselRepository`
- [ ] Return success JSON

### 9.4 — SaveContent (`wp_ajax_wphz_ugc_save_content`)
- [ ] Verify nonce `wphz_ugc_admin`
- [ ] Delete all existing items for `carousel_id = 'default'`
- [ ] Re-insert from `$_POST['items']` with incrementing `sort_order`
- [ ] Skip items missing `video_id` or `video_url`
- [ ] Return success JSON

### 9.5 — ProductSearch (`wp_ajax_wphz_ugc_product_search`)
- [ ] Verify nonce `wphz_ugc_admin`
- [ ] Min 2 chars required
- [ ] Check transient cache first
- [ ] Query via `WC_Product_Query` (limit 15, published)
- [ ] Map results: `id`, `name`, `price_html`, `thumbnail`
- [ ] Cache results, return success JSON

### 9.6 — DeleteItem (`wp_ajax_wphz_ugc_delete_item`)
- [ ] Verify nonce `wphz_ugc_admin`
- [ ] Get `id` from POST, delete via `ItemRepository`
- [ ] Return success/error

---

## Full Code

See `plan.md` sections 9.1–9.5 for complete code of each file.

---

## Verification

- [ ] All handlers return 403 on nonce failure
- [ ] SaveConfig saves both config and danger config correctly
- [ ] SaveContent is idempotent (saving twice doesn't duplicate)
- [ ] ProductSearch returns cached results on repeated queries
- [ ] DeleteItem removes item and returns success
