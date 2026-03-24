# Phase 8 — WooCommerce Add-to-Cart Integration

> **Goal:** ATC buttons in product cards fire WC's AJAX add-to-cart without page reload, show feedback, and update the WC cart fragment.  
> **Dependencies:** WooCommerce must be active (guarded in Phase 1). Phase 6 template renders buttons with `data-product-id` and `data-nonce`.

---

## Implementation Order

```
Phase 1 ✅ → Phase 2 ✅ → Phase 9 ✅ → Phase 3 ✅ → Phase 4 ✅ → Phase 5 ✅ → Phase 6 ✅ → Phase 7 ✅ → [Phase 8]
```

---

## Files to Create / Modify

| # | File Path | Purpose |
|---|-----------|---------|
| 1 | `src/Frontend/CartHandler.php` | WC add-to-cart AJAX bridge |
| 2 | Append to `assets/frontend/carousel.js` | ATC JS click handler |

---

## Checklist

### 8.1 — `src/Frontend/CartHandler.php`
- [ ] Namespace: `WPHZ\UGC\Frontend`
- [ ] Extends `AbstractSingleton`
- [ ] `init()`: register AJAX actions for both logged-in and anonymous users:
  - [ ] `wp_ajax_wphz_ugc_add_to_cart` → `handle()`
  - [ ] `wp_ajax_nopriv_wphz_ugc_add_to_cart` → `handle()`
- [ ] `handle()`:
  - [ ] Verify nonce via `NonceHelper::verify('wphz_ugc_atc')`
  - [ ] Get `product_id` from `$_POST` (cast to int)
  - [ ] Get `quantity` from `$_POST` (cast to int, default 1)
  - [ ] Validate: if `!$product_id` → `wp_send_json_error(['message' => 'Invalid product.'])`
  - [ ] Get product via `wc_get_product($product_id)`
  - [ ] Validate: if product doesn't exist or not purchasable → `wp_send_json_error(['message' => 'Product not purchasable.'])`
  - [ ] Add to cart via `WC()->cart->add_to_cart($product_id, $quantity)`
  - [ ] On success: call `WC_AJAX::get_refreshed_fragments()` (sends JSON and exits)
  - [ ] On failure: `wp_send_json_error(['message' => 'Could not add to cart.'])`

```php
<?php
namespace WPHZ\UGC\Frontend;

use WPHZ\UGC\AbstractSingleton;

class CartHandler extends AbstractSingleton {

    public function init(): void {
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

        $result = WC()->cart->add_to_cart($product_id, $quantity);

        if ($result) {
            WC_AJAX::get_refreshed_fragments();
        } else {
            wp_send_json_error(['message' => 'Could not add to cart.']);
        }
    }
}
```

### 8.2 — ATC JavaScript (append to `assets/frontend/carousel.js`)
- [ ] Delegated click handler on `document` for `.wphz-ugc-atc-btn`
- [ ] Read `data-product-id` and `data-nonce` from button
- [ ] Show loading state: set button text to `'...'`, disable button
- [ ] POST to `wphzUGCFrontend.ajaxurl` with:
  - [ ] `action: 'wphz_ugc_add_to_cart'`
  - [ ] `product_id`
  - [ ] `quantity: 1`
  - [ ] `nonce`
- [ ] On success:
  - [ ] Show `wphzUGCFrontend.i18n.added` text ("Added!")
  - [ ] Dispatch `wc_fragment_refresh` event on `document.body` to update mini-cart
- [ ] On error:
  - [ ] Show `wphzUGCFrontend.i18n.error` text ("Error — retry.")
- [ ] Finally (after 2.5s):
  - [ ] Reset button text to original
  - [ ] Re-enable button

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

## Important Notes

- This handler works for **simple products**. For variable/grouped products, additional logic would be needed in v2.
- `WC_AJAX::get_refreshed_fragments()` sends JSON response AND exits — no code after it will execute.
- The `data-nonce` is per-button, generated in `templates/frontend/product-item.php` with `wp_create_nonce('wphz_ugc_atc')`.

---

## Verification

- [ ] ATC button adds product to WC cart via AJAX (no page reload)
- [ ] WC cart fragment updates (mini-cart count refreshes)
- [ ] Button shows "Added!" then resets after 2.5s
- [ ] Button is disabled during AJAX call (no double-clicks)
- [ ] Non-purchasable product returns error JSON gracefully
- [ ] Invalid product ID returns error JSON
- [ ] Nonce failure returns 403
- [ ] Works for both logged-in and anonymous users
