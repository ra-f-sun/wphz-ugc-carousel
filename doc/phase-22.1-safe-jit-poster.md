# Phase 22.1 — Safe Poster + JIT Video Governor

## Goal
Deliver a stable, low-risk video memory optimization path that keeps non-active slides in poster state and loads video sources just-in-time for active playback.

This phase intentionally avoids high-risk cross-origin canvas extraction and does not promise strict zero-memory growth across all devices/browsers.

## Scope

### Included
- Optional per-item `poster_url` persisted in DB and admin UI.
- Native poster-first inactive state.
- Just-in-time video source attach for active slide playback.
- Source detach on deactivation to reduce decoder/cache pressure.
- Dedicated governor module separated from carousel navigation logic.
- Explicit cross-origin mode via `crossorigin="anonymous"`.
- Lightweight diagnostics for media/CORS failures.

### Excluded (by design)
- Canvas-based first-frame extraction requirement.
- Base64 poster generation pipeline.
- Plugin-side CORS bypass/proxy/header rewriting.
- Hard guarantee of flatline RAM across all devices.

## Architecture

### Data + Admin
- `src/Installer/Installer.php`: `poster_url` migration for `wphz_ugc_items`.
- `src/Repository/ItemRepository.php`: `poster_url` map in insert/update.
- `src/Ajax/SaveContent.php`: sanitize/store `poster_url`.
- `templates/admin/item-row.php`: poster input + image picker button.
- `templates/admin/content-tab.php`: poster field in JS row template.
- `assets/admin/admin.js`: image media picker + payload serialization.

### Frontend
- `templates/frontend/carousel-item.php`:
  - emits `data-poster-url`
  - emits `poster`
  - emits `crossorigin="anonymous"`
  - uses `preload="none"`
- `assets/frontend/poster-engine.js`:
  - `applyPoster(video)`
  - `attachSource(video)`
  - `detachSource(video)`
  - `play(video, isMuted)`
  - `pause(video)`
- `assets/frontend/carousel.js`:
  - selects source into `data-selected-src` only (no eager `src` assignment)
  - delegates active playback and inactive reset/detach to governor
- `src/Frontend/AssetLoader.php`:
  - enqueues governor before `carousel.js`

## Runtime Behavior

1. On render, videos have poster metadata and no eager preload intent (`preload=none`).
2. On init, resolution selection stores chosen URL in `data-selected-src`.
3. On active slide play:
   - governor attaches `src`
   - sets `preload=metadata`
   - calls `load()` then `play()`
4. On slide deactivation:
   - pause + reset to poster
   - remove `src` + `load()` to release resources
5. On media error:
   - console warning explains cross-origin host headers may need fixing.

## Acceptance Criteria

- Initial load does not pull full media for non-active slides.
- Active slide starts video via JIT source attach.
- Inactive slide returns to poster state and detaches source.
- Missing poster does not break playback flow.
- External-host CORS failures are diagnosable and not silently masked.
- No regressions in carousel navigation, snapback, product sub-carousel, or add-to-cart flows.

## Verification Checklist

- Admin save/reload persists `poster_url`.
- Frontend `<video>` includes poster + crossorigin attributes.
- Network tab shows no non-active heavy media fetch on initial load.
- Next/prev/drag/snapback repeatedly with no freeze/crash.
- iOS Safari multi-slide stress test remains stable relative to prior behavior.

## Risks & Notes

- Browser media memory policies vary; optimization is best-effort, not absolute.
- Third-party hosts must provide proper CORS/media headers.
- JIT attach may slightly increase first-play latency per slide.
