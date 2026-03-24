# Phase 6 — Frontend Templates & CSS

> **Goal:** Pixel-matching HTML output. Pure HTML + CSS (+ BEM class naming). Zero JS in templates.  
> **Dependencies:** Phase 5 provides template data. Phase 7 will attach JS behavior to CSS classes/data-attrs defined here.

---

## Implementation Order

```
Phase 1 ✅ → Phase 2 ✅ → Phase 9 ✅ → Phase 3 ✅ → Phase 4 ✅ → Phase 5 ✅ → [Phase 6] → Phase 7 → Phase 8
```

---

## Files to Create

| # | File Path | Purpose |
|---|-----------|---------|
| 1 | `templates/frontend/carousel-wrapper.php` | Outer carousel section HTML |
| 2 | `templates/frontend/carousel-item.php` | Single video slide HTML |
| 3 | `templates/frontend/product-item.php` | Single product card HTML |
| 4 | `assets/frontend/carousel.css` | All frontend carousel CSS |

---

## CSS Class / Data-Attribute Contract (Phase 7 JS reads these)

| HTML Element | Class / Attr | Purpose |
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
| `data-muted` | on `.wphz-ugc-carousel` | `'1'` or `'0'` |
| `data-direction` | on `.wphz-ugc-carousel` | `'ltr'` or `'rtl'` |
| `data-index` | on `.wphz-ugc-slide` | Slide index (int) |
| `data-nonce` | on `.wphz-ugc-atc-btn` | ATC nonce |

> ⚠️ **If any class or data-attr is renamed here, Phase 7 JS MUST be updated to match.**

---

## Checklist

### 6.1 — `templates/frontend/carousel-wrapper.php`
- [ ] ABSPATH guard
- [ ] `<section class="wphz-ugc-carousel">` with data attrs:
  - [ ] `data-muted="1|0"` from `$is_muted`
  - [ ] `data-direction="ltr|rtl"` from `$slide`
- [ ] Header section (`.wphz-ugc-header`):
  - [ ] Text block (`.wphz-ugc-header__text`):
    - [ ] `<h2 class="wphz-ugc-heading">` — conditionally rendered
    - [ ] `<p class="wphz-ugc-subheading">` — conditionally rendered
  - [ ] Arrows block (`.wphz-ugc-arrows`):
    - [ ] Left arrow: `<button class="wphz-ugc-arrow wphz-ugc-arrow--left">`
    - [ ] Right arrow: `<button class="wphz-ugc-arrow wphz-ugc-arrow--right">`
- [ ] Stage (`.wphz-ugc-stage`):
  - [ ] Track (`.wphz-ugc-track`):
    - [ ] Loop through `$items`, render each via `TemplateLoader::render('frontend/carousel-item', [...])`
    - [ ] Pass: `item`, `index`, `is_muted`, `active` (true for index === 0)

### 6.2 — `templates/frontend/carousel-item.php`
- [ ] ABSPATH guard
- [ ] Determine if multi-product: `count($products) > 1`
- [ ] `<div class="wphz-ugc-slide {active_class}" data-index="{index}">`
- [ ] Video wrapper (`.wphz-ugc-video-wrap`):
  - [ ] `<video class="wphz-ugc-video">` with attrs:
    - [ ] `src` from item video_url
    - [ ] Conditionally add `muted` attribute
    - [ ] `playsinline`
    - [ ] `preload="auto"` for active, `"metadata"` for others
    - [ ] `loop="false"`
  - [ ] Mute button (`.wphz-ugc-mute-btn`):
    - [ ] `.wphz-icon-mute` span (visible when muted)
    - [ ] `.wphz-icon-unmute` span (hidden when muted, `display:none`)
    - [ ] Include appropriate SVG icons for mute/unmute
- [ ] Products section (conditional — only if products exist):
  - [ ] `<div class="wphz-ugc-products {multi-class}">`
  - [ ] Products track (`.wphz-ugc-products-track`):
    - [ ] Loop products, render each via `TemplateLoader::render('frontend/product-item', [...])`
  - [ ] If multi-product: render prev/next arrows
    - [ ] `<button class="wphz-product-arrow wphz-product-arrow--prev">`
    - [ ] `<button class="wphz-product-arrow wphz-product-arrow--next">`

### 6.3 — `templates/frontend/product-item.php`
- [ ] ABSPATH guard
- [ ] `<div class="wphz-ugc-product-item">`
- [ ] Product image (`.wphz-ugc-product-img`):
  - [ ] Use `$product->get_image('thumbnail')`
- [ ] Product info (`.wphz-ugc-product-info`):
  - [ ] Name: `$product->get_name()` in `.wphz-ugc-product-name`
  - [ ] Price: `$product->get_price_html()` in `.wphz-ugc-product-price` (use `wp_kses_post()`)
- [ ] Add to Cart button (`.wphz-ugc-atc-btn`):
  - [ ] `data-product-id` from `$product->get_id()`
  - [ ] `data-nonce` from `wp_create_nonce('wphz_ugc_atc')`
  - [ ] Button text: "Add to Cart"

### 6.4 — `assets/frontend/carousel.css`
- [ ] Stage clips overflow: `.wphz-ugc-stage { overflow: hidden; position: relative; }`
- [ ] Track flex layout with transition:
  ```css
  .wphz-ugc-track {
      display: flex;
      transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
      will-change: transform;
  }
  ```
- [ ] Desktop: 3 slides visible: `.wphz-ugc-slide { flex: 0 0 calc(100% / 3); padding: 0 8px; box-sizing: border-box; }`
- [ ] Active slide scale: `.wphz-ugc-slide--active .wphz-ugc-video-wrap { transform: scale(1.04); }`
- [ ] Video fills slide: `.wphz-ugc-video { width: 100%; aspect-ratio: 9/16; object-fit: cover; border-radius: 12px; }`
- [ ] Product strip flex layout:
  ```css
  .wphz-ugc-products { display: flex; align-items: center; gap: 8px; padding: 8px 0; overflow: hidden; }
  .wphz-ugc-products--carousel { position: relative; }
  ```
- [ ] Mobile responsive (≤768px): `.wphz-ugc-slide { flex: 0 0 80%; }`
- [ ] Style arrows, mute button, product cards, ATC buttons
- [ ] Style header section with heading/subheading
- [ ] Smooth transitions for all interactive elements

---

## Verification

- [ ] Desktop: 3 slides visible, center slide scaled up
- [ ] Mobile (≤768px): 1 slide takes ~80% width
- [ ] Product section: single product = full width static; multiple = sub-carousel with arrows
- [ ] All CSS classes match the contract table above exactly
- [ ] All data-attributes are correctly set in HTML
- [ ] No JS in any template file
- [ ] Mute/unmute icons are present (SVG or icon font)
- [ ] Video has correct attributes (playsinline, preload, muted)
