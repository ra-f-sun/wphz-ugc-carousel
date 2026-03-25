# Phase 22.1 Implementation Handoff

## 1) Context and Objective

This implementation introduces a **poster-first + safe JIT video lifecycle** for the UGC carousel.

Primary goals:
- Show poster images for inactive slides when available.
- Load video sources **just-in-time** for active playback.
- Release inactive video resources to reduce decoder/memory pressure.
- Keep a safe fallback for slides without poster URL (avoid black tiles).
- Keep CORS handling explicit (`crossorigin="anonymous"`) without plugin-side bypass.

This is intentionally the **safe variant** (Phase 22.1), not the high-risk canvas extraction variant.

---

## 2) High-Level Design (Why this architecture)

### Why split into a separate governor
Carousel navigation logic and video lifecycle logic are different concerns.

- `assets/frontend/carousel.js` remains responsible for:
  - track cloning/infinite navigation,
  - drag/swipe,
  - active-index movement.
- `assets/frontend/poster-engine.js` is responsible for:
  - poster application,
  - source attach/detach,
  - play/pause lifecycle behavior,
  - diagnostics.

This separation lowers coupling and makes performance policies tunable without destabilizing navigation.

### Why safe JIT (not canvas extraction)
- Canvas extraction across third-party origins is CORS-sensitive and inconsistent.
- Safe JIT gives predictable behavior now while preserving future extensibility.
- We avoid brittle promises like strict flat RAM across all browser/device combinations.

---

## 3) End-to-End Flow

1. Admin user sets `video_url_hd`, `video_url_sd`, optional `poster_url`.
2. AJAX save stores row in `wphz_ugc_items` with `poster_url`.
3. Frontend template renders `<video>` with:
   - `data-src-hd`
   - `data-src-sd`
   - `data-poster-url`
   - `poster`
   - `crossorigin="anonymous"`
   - `preload="none"`
4. Carousel computes per-slide selected source URL and stores it in `data-selected-src`.
5. Poster engine:
   - attaches source on active play,
   - detaches source on inactive reset when poster exists,
   - keeps metadata source for no-poster slides to avoid dark/black tile fallback.

---

## 4) File-by-File Changes

## A) Plugin bootstrap and migration safety

### `wphz-ugc-carousel.php`
- Bumped version to trigger migration path:

```php
 * Version:     1.0.5
define('WPHZ_UGC_VERSION',   '1.0.5');
```

### `src/Plugin.php`
- Added runtime schema preflight in `init` to auto-run installer when `poster_url` is missing, even if option version appears current:

```php
add_action('init', function () {
    if (
        get_option('wphz_ugc_db_version') !== WPHZ_UGC_VERSION
        || !\WPHZ\UGC\Installer\Installer::items_has_poster_column()
    ) {
        \WPHZ\UGC\Installer\Installer::activate();
    }
});
```

**Why:** prevents production breakage where save path expects `poster_url` but DB schema is stale.

---

## B) Installer + schema

### `src/Installer/Installer.php`
- Added `items_has_poster_column()` helper.
- Added `poster_url` in table DDL and safe migration block:

```php
public static function items_has_poster_column(): bool
{
    global $wpdb;
    $table_items = $wpdb->prefix . 'wphz_ugc_items';
    return (bool) $wpdb->get_var("SHOW COLUMNS FROM {$table_items} LIKE 'poster_url'");
}

// In CREATE TABLE
poster_url text DEFAULT NULL,

// Safe migration
if (! $wpdb->get_var("SHOW COLUMNS FROM {$table_items} LIKE 'poster_url'")) {
    $wpdb->query("ALTER TABLE {$table_items} ADD COLUMN poster_url text DEFAULT NULL");
}
```

**Why:** idempotent migration for fresh installs and upgrades.

---

## C) Repository + save pipeline

### `src/Repository/ItemRepository.php`
- Added `poster_url` to `insert()` and `update()` maps:

```php
'poster_url'   => esc_url_raw($data['poster_url'] ?? ''),

if (isset($data['poster_url']))   $fields['poster_url']   = esc_url_raw($data['poster_url']);
```

### `src/Ajax/SaveContent.php`
- Parses and sanitizes poster URL.
- Adds schema preflight guard and explicit insert failure handling.

```php
$poster_url   = esc_url_raw($data['poster_url'] ?? '');

if (!Installer::items_has_poster_column()) {
    wp_send_json_error([
        'message' => __('Database schema is outdated. Please reload the page and try again.', 'wphz-ugc')
    ]);
}

if (!$repo->insert([
    'carousel_id'  => (string) $carousel_id,
    'sort_order'   => $sort++,
    'video_id'     => $video_id,
    'video_url_hd' => $video_url_hd,
    'video_url_sd' => $video_url_sd,
    'poster_url'   => $poster_url,
    'product_ids'  => $product_ids,
])) {
    global $wpdb;
    wp_send_json_error([
        'message' => __('Failed to save carousel content. Please retry after refreshing the page.', 'wphz-ugc'),
        'debug'   => $wpdb->last_error,
    ]);
}
```

**Why:** avoid silent data loss behavior in delete-then-insert strategy.

---

## D) Admin authoring UX

### `templates/admin/item-row.php`
- Added poster input row and image-specific media button:

```php
<input type="url"
       name="items[<?php echo esc_attr($row_id); ?>][poster_url]"
       value="<?php echo esc_url($poster_url); ?>"
       placeholder="https://..."
       class="wphz-url-input regular-text">
<button type="button" class="button wphz-media-btn" data-media-type="image">
    <?php esc_html_e('Media Library', 'wphz-ugc'); ?>
</button>
```

### `templates/admin/content-tab.php`
- Added same poster field in JS template (`tmpl-wphz-item-row`) for newly created rows.

### `assets/admin/admin.js`
- Media picker now respects `data-media-type` (`image` for poster, `video` for HD/SD).
- Includes `poster_url` in serialized payload:

```js
var mediaType = ($btn.data('media-type') || 'video').toString();
var isImage = mediaType === 'image';

var mediaFrame = wp.media({
  title: isImage ? (wphzUGC.imageTitle || 'Select Image') : (wphzUGC.mediaTitle || 'Select Video'),
  button: { text: isImage ? (wphzUGC.imageButton || 'Use this image') : (wphzUGC.mediaButton || 'Use this video') },
  library: { type: isImage ? 'image' : 'video' },
  multiple: false,
});

var posterUrl = $row.find('[name="items[' + rowId + '][poster_url]"]').val();

items[index] = {
  video_id: videoId,
  video_url_hd: videoUrlHd,
  video_url_sd: videoUrlSd,
  poster_url: posterUrl,
  products: products,
};
```

**Why:** direct authoring path for poster images and explicit media-type scoping.

---

## E) Frontend template contract

### `templates/frontend/carousel-item.php`
- Video element now carries poster + CORS + JIT preload hints:

```php
<video class="<?php echo esc_attr($video_class); ?>"
    data-src-hd="<?php echo esc_url($item['video_url_hd'] ?? ''); ?>"
    data-src-sd="<?php echo esc_url($item['video_url_sd'] ?? ''); ?>"
    data-poster-url="<?php echo esc_url($poster_url); ?>"
    poster="<?php echo esc_url($poster_url); ?>"
    crossorigin="anonymous"
    playsinline
    <?php if ($is_muted) echo 'muted'; ?>
    preload="none"></video>
```

**Why:** explicit runtime contract for governor to control source lifecycle.

---

## F) Script loading order

### `src/Frontend/AssetLoader.php`
- Enqueues `poster-engine.js` before `carousel.js`:

```php
wp_enqueue_script(
    'wphz-ugc-poster-engine',
    WPHZ_UGC_URL . 'assets/frontend/poster-engine.js',
    [],
    WPHZ_UGC_VERSION,
    true
);

wp_enqueue_script(
    'wphz-ugc-frontend',
    WPHZ_UGC_URL . 'assets/frontend/carousel.js',
    ['wphz-ugc-poster-engine'],
    WPHZ_UGC_VERSION,
    true
);
```

**Why:** governor class must exist before carousel invokes it.

---

## G) Runtime governor logic

### `assets/frontend/poster-engine.js`
Core methods:
- `applyPoster(video)`
- `attachSource(video)`
- `detachSource(video)`
- `play(video, isMuted)`
- `pause(video)`

Key behavior:

```js
resetToPoster(video) {
  video.pause();
  try { video.currentTime = 0; } catch (error) {}

  if (this._hasPoster(video)) {
    this.detachSource(video);
  } else {
    // no-poster fallback to avoid black tiles
    this.attachSource(video);
  }
  this.applyPoster(video);
}
```

```js
attachSource(video) {
  const selectedSrc = (video.dataset.selectedSrc || "").trim();
  if (!selectedSrc) {
    this.applyPoster(video);
    return;
  }

  if (video.getAttribute("src") !== selectedSrc) {
    video.setAttribute("src", selectedSrc);
    video.preload = "metadata";
    video.load();
  }

  this.applyPoster(video);
}
```

**Why:** reduces background memory use while preserving UX fallback for missing posters.

---

## H) Carousel integration points

### `assets/frontend/carousel.js`
- Source selection stores URL in `data-selected-src` (no eager direct `src` assignment).
- No-poster fallback eagerly attaches metadata source.
- Playback lifecycle delegated to governor.

```js
video.dataset.selectedSrc = targetSrc;
this.posterEngine?.applyPoster(video);

const hasPoster = !!(video.dataset.posterUrl || "").trim();
if (!hasPoster && this.posterEngine) {
  this.posterEngine.attachSource(video);
}
```

```js
const playPromise = this.posterEngine
  ? this.posterEngine.play(video, this.isMuted)
  : (() => {
      video.muted = this.isMuted;
      return video.play();
    })();
```

```js
if (this.posterEngine) {
  this.posterEngine.pause(video);
} else {
  video.pause();
  video.currentTime = 0;
}
```

**Why:** centralizes media behavior and keeps existing autoplay fallback behavior intact.

---

## 5) Behavior Matrix

### With `poster_url`
- Inactive slide: poster shown, source detached on pause/reset.
- Active slide: source attached JIT and plays.
- Leaving active state: returns to poster-only state.

### Without `poster_url`
- Inactive slide: source kept in metadata mode so first-frame fallback can appear (not black).
- Active slide: plays normally from selected source.

---

## 6) Key Decisions and Tradeoffs

### Decision 1: Safe JIT over canvas extraction
- Chosen for reliability and cross-origin variability control.
- Avoids fragile dependency on remote CORS + canvas taint edge cases.

### Decision 2: Explicit schema preflight checks
- Prevents false success when save path depends on a column that may not exist yet.

### Decision 3: `crossorigin="anonymous"`
- Explicit credential-less cross-origin mode.
- Clarifies ownership: CDN/origin must provide correct headers.

### Tradeoffs
- JIT can introduce slight first-play latency on newly active slides.
- No-poster fallback may create more metadata requests (intended UX tradeoff to avoid dark tiles).
- Browser memory handling is optimized, not mathematically guaranteed.

---

## 7) What to Highlight in Senior Review

- End-to-end consistency: schema → admin form → save API → template → runtime governor.
- Non-breaking migration safety for upgraded installs.
- Separation of concerns (navigation vs media lifecycle).
- Explicit failure visibility (`wp_send_json_error` with DB error context).
- CORS boundary correctness: plugin does not fake remote CORS compatibility.

---

## 8) Validation Checklist

- Save a carousel with poster URL; refresh admin; confirm persistence.
- Save without poster URL; verify inactive slides are not black.
- Frontend inspect `<video>` for `poster`, `data-poster-url`, `crossorigin`.
- Network tab:
  - no bulk full-media download on initial load,
  - active slide attaches source and plays,
  - inactive poster-enabled slides release source.
- Rapid next/prev/drag/snapback stress test.
- Confirm product carousel and add-to-cart still work.

---

## 9) Related docs

- `doc/phase-22.1-safe-jit-poster.md` (phase specification)
- This file (`doc/phase-22.1-implementation-handoff.md`) (implementation deep dive)
