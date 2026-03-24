# Phase 7 — Frontend JavaScript (Carousel Engine)

> **Goal:** Implement all carousel behaviors as a self-contained ES6 class in a single file. The class reads DOM data-attributes and CSS classes defined in Phase 6. It has ZERO coupling to PHP — it only reads `wphzUGCFrontend` localized object.  
> **Dependencies:** Phase 6 (DOM contract), Phase 3 (Danger Zone events injected via `wp_localize_script`)

---

## Implementation Order

```
Phase 1 ✅ → Phase 2 ✅ → Phase 9 ✅ → Phase 3 ✅ → Phase 4 ✅ → Phase 5 ✅ → Phase 6 ✅ → [Phase 7] → Phase 8
```

---

## Files to Create

| # | File Path | Purpose |
|---|-----------|---------|
| 1 | `assets/frontend/carousel.js` | Complete carousel engine + product sub-carousel |

---

## Architecture

Two ES6 classes + one boot function:

| Class | Purpose |
|---|---|
| `WPHZUGCCarousel` | Main video carousel — sliding, video control, drag/swipe, arrows, mute, danger events |
| `WPHZProductCarousel` | Per-slide product sub-carousel (prev/next within product strip) |

Boot on `DOMContentLoaded`: instantiate both for all matching DOM elements.

---

## Checklist

### 7.1 — `WPHZUGCCarousel` Class

#### Constructor
- [ ] Accept root element `el`
- [ ] Query and store: `.wphz-ugc-track`, all `.wphz-ugc-slide` elements
- [ ] Initialize state: `current = 0`, `totalSlides`, `isMuted` (from `data-muted`), `direction` (from `data-direction`)
- [ ] Initialize drag state: `{ active: false, startX: 0, diffX: 0 }`
- [ ] Call `init()`

#### `init()` — Wire All Event Listeners
- [ ] `_bindArrows()`
- [ ] `_bindDrag()`
- [ ] `_bindMuteButtons()`
- [ ] `_autoPlayCurrent()`
- [ ] `_updateSlideClasses()`

#### Slide Navigation
- [ ] `goTo(index)`:
  - [ ] Pause current video
  - [ ] Clamp index (wrap around)
  - [ ] Update slide classes
  - [ ] Apply transform
  - [ ] Auto-play new current video
- [ ] `next()` → `goTo(current + 1)`
- [ ] `prev()` → `goTo(current - 1)`
- [ ] `_clamp(index)`:
  - [ ] `< 0` → `totalSlides - 1`
  - [ ] `>= totalSlides` → `0`

#### Transform
- [ ] `_applyTransform()`:
  - [ ] Calculate visible count (3 desktop, 1 mobile at ≤768px)
  - [ ] Offset = `current - Math.floor(visibleCount / 2)` (center active)
  - [ ] Percentage: `(100 / visibleCount) * offset`
  - [ ] Set `track.style.transform = translateX(-${pct}%)`
- [ ] `_getVisibleCount()`: returns 1 if `window.innerWidth <= 768`, else 3

#### CSS Class Management
- [ ] `_updateSlideClasses()`:
  - [ ] Toggle `.wphz-ugc-slide--active` on current slide only

#### Video Control
- [ ] `_autoPlayCurrent()`:
  - [ ] Get video element at current slide
  - [ ] Set `video.muted` based on `isMuted`
  - [ ] Call `video.play()` (catch errors silently)
  - [ ] Set `video.onended` → `_onVideoEnded()`
- [ ] `_pauseVideo(index)`:
  - [ ] Pause video, reset `currentTime = 0`
- [ ] `_getVideoAt(index)`:
  - [ ] Query `.wphz-ugc-video` inside slide at index
- [ ] `_onVideoEnded()`:
  - [ ] If direction is `'rtl'` → `prev()`, else → `next()`

#### Arrow Buttons
- [ ] `_bindArrows()`:
  - [ ] Right arrow click → `next()` + `_fireDangerEvent('right')`
  - [ ] Left arrow click → `prev()` + `_fireDangerEvent('left')`

#### Danger Zone Event Firing
- [ ] `_fireDangerEvent(direction)`:
  - [ ] Read config from `window.wphzUGCFrontend.danger`
  - [ ] Get event name based on direction (`on_arrow_right` / `on_arrow_left`)
  - [ ] If name is empty, return
  - [ ] Dispatch `CustomEvent` on `document` with `{ detail: { direction, slideIndex } }`
  - [ ] If `window[name]` is a function, call it with `(direction, slideIndex)` args

#### Drag / Swipe
- [ ] `_bindDrag()`:
  - [ ] Mouse events: `mousedown` → start, `mousemove` → move, `mouseup` → end
  - [ ] Touch events: `touchstart`, `touchmove`, `touchend` (all passive)
- [ ] `_onDragStart(x)`: set drag active, store startX, disable transition
- [ ] `_onDragMove(x)`: calculate diffX
- [ ] `_onDragEnd()`:
  - [ ] Re-enable transition
  - [ ] Threshold: 60px
  - [ ] If dragged left > threshold → `next()`
  - [ ] If dragged right > threshold → `prev()`
  - [ ] Else → snap back (`_applyTransform()`)

#### Per-Slide Mute Toggle
- [ ] `_bindMuteButtons()`:
  - [ ] Delegated click handler on root for `.wphz-ugc-mute-btn`
  - [ ] Toggle `video.muted`
  - [ ] Toggle visibility of `.wphz-icon-mute` and `.wphz-icon-unmute`

### 7.2 — `WPHZProductCarousel` Class
- [ ] Constructor: accept wrapper element, query `.wphz-ugc-products-track` and all `.wphz-ugc-product-item`
- [ ] `_bindArrows()`: bind `.wphz-product-arrow--next` and `.wphz-product-arrow--prev`
- [ ] `_slide(dir)`:
  - [ ] Clamp current between 0 and `items.length - 1`
  - [ ] Set `track.style.transform = translateX(-${current * 100}%)`

### 7.3 — Boot Function
- [ ] On `DOMContentLoaded`:
  - [ ] Instantiate `WPHZUGCCarousel` for each `.wphz-ugc-carousel`
  - [ ] Instantiate `WPHZProductCarousel` for each `.wphz-ugc-products--carousel`

### 7.4 — Full Code Reference

```javascript
class WPHZUGCCarousel {
    constructor(el) {
        this.root        = el;
        this.track       = el.querySelector('.wphz-ugc-track');
        this.slides      = Array.from(el.querySelectorAll('.wphz-ugc-slide'));
        this.totalSlides = this.slides.length;
        this.current     = 0;
        this.isMuted     = el.dataset.muted === '1';
        this.direction   = el.dataset.direction || 'ltr';
        this._drag = { active: false, startX: 0, diffX: 0 };
        this.init();
    }

    init() {
        this._bindArrows();
        this._bindDrag();
        this._bindMuteButtons();
        this._autoPlayCurrent();
        this._updateSlideClasses();
    }

    goTo(index) {
        this._pauseVideo(this.current);
        this.current = this._clamp(index);
        this._updateSlideClasses();
        this._applyTransform();
        this._autoPlayCurrent();
    }

    next() { this.goTo(this.current + 1); }
    prev() { this.goTo(this.current - 1); }

    _clamp(index) {
        if (index < 0) return this.totalSlides - 1;
        if (index >= this.totalSlides) return 0;
        return index;
    }

    _applyTransform() {
        const visibleCount = this._getVisibleCount();
        const offset       = this.current - Math.floor(visibleCount / 2);
        const pct          = (100 / visibleCount) * offset;
        this.track.style.transform = `translateX(-${pct}%)`;
    }

    _getVisibleCount() {
        return window.innerWidth <= 768 ? 1 : 3;
    }

    _updateSlideClasses() {
        this.slides.forEach((slide, i) => {
            slide.classList.toggle('wphz-ugc-slide--active', i === this.current);
        });
    }

    _autoPlayCurrent() {
        const video = this._getVideoAt(this.current);
        if (!video) return;
        video.muted = this.isMuted;
        video.play().catch(() => {});
        video.onended = () => this._onVideoEnded();
    }

    _pauseVideo(index) {
        const video = this._getVideoAt(index);
        if (video) { video.pause(); video.currentTime = 0; }
    }

    _getVideoAt(index) {
        return this.slides[index]?.querySelector('.wphz-ugc-video') || null;
    }

    _onVideoEnded() {
        this.direction === 'rtl' ? this.prev() : this.next();
    }

    _bindArrows() {
        const rightBtn = this.root.querySelector('.wphz-ugc-arrow--right');
        const leftBtn  = this.root.querySelector('.wphz-ugc-arrow--left');
        rightBtn?.addEventListener('click', () => {
            this.next();
            this._fireDangerEvent('right');
        });
        leftBtn?.addEventListener('click', () => {
            this.prev();
            this._fireDangerEvent('left');
        });
    }

    _fireDangerEvent(direction) {
        const config = window.wphzUGCFrontend?.danger || {};
        const name   = direction === 'right' ? config.on_arrow_right : config.on_arrow_left;
        if (!name) return;
        const detail = { direction, slideIndex: this.current };
        document.dispatchEvent(new CustomEvent(name, { detail }));
        if (typeof window[name] === 'function') {
            window[name](direction, this.current);
        }
    }

    _bindDrag() {
        const track = this.track;
        track.addEventListener('mousedown', e => this._onDragStart(e.clientX));
        window.addEventListener('mousemove', e => this._onDragMove(e.clientX));
        window.addEventListener('mouseup',   ()  => this._onDragEnd());
        track.addEventListener('touchstart', e => this._onDragStart(e.touches[0].clientX), { passive: true });
        track.addEventListener('touchmove',  e => this._onDragMove(e.touches[0].clientX),  { passive: true });
        track.addEventListener('touchend',   ()  => this._onDragEnd());
    }

    _onDragStart(x)  { this._drag = { active: true, startX: x, diffX: 0 }; this.track.style.transition = 'none'; }
    _onDragMove(x)   { if (!this._drag.active) return; this._drag.diffX = x - this._drag.startX; }
    _onDragEnd()     {
        if (!this._drag.active) return;
        this._drag.active = false;
        this.track.style.transition = '';
        const threshold = 60;
        if (this._drag.diffX < -threshold) this.next();
        else if (this._drag.diffX > threshold) this.prev();
        else this._applyTransform();
    }

    _bindMuteButtons() {
        this.root.addEventListener('click', e => {
            const btn = e.target.closest('.wphz-ugc-mute-btn');
            if (!btn) return;
            const slide = btn.closest('.wphz-ugc-slide');
            const video = slide?.querySelector('.wphz-ugc-video');
            if (!video) return;
            video.muted = !video.muted;
            btn.querySelector('.wphz-icon-mute').style.display  = video.muted ? '' : 'none';
            btn.querySelector('.wphz-icon-unmute').style.display = video.muted ? 'none' : '';
        });
    }
}

class WPHZProductCarousel {
    constructor(el) {
        this.wrap    = el;
        this.track   = el.querySelector('.wphz-ugc-products-track');
        this.items   = Array.from(el.querySelectorAll('.wphz-ugc-product-item'));
        this.current = 0;
        this._bindArrows();
    }
    _bindArrows() {
        this.wrap.querySelector('.wphz-product-arrow--next')?.addEventListener('click', () => this._slide(1));
        this.wrap.querySelector('.wphz-product-arrow--prev')?.addEventListener('click', () => this._slide(-1));
    }
    _slide(dir) {
        const max    = this.items.length - 1;
        this.current = Math.max(0, Math.min(max, this.current + dir));
        const pct    = this.current * 100;
        this.track.style.transform = `translateX(-${pct}%)`;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.wphz-ugc-carousel').forEach(el => new WPHZUGCCarousel(el));
    document.querySelectorAll('.wphz-ugc-products--carousel').forEach(el => new WPHZProductCarousel(el));
});
```

---

## Verification

- [ ] Center video autoplays on load (muted by default)
- [ ] When video ends → next slide auto-advances (or prev in RTL mode)
- [ ] Arrow right → advances forward; Arrow left → goes back
- [ ] Drag right → prev; Drag left → next (above 60px threshold)
- [ ] Drag below threshold → snaps back
- [ ] Mute toggle per slide works independently
- [ ] Danger zone: if `on_arrow_right` = `myFn` and `window.myFn` exists → called with `(direction, slideIndex)`
- [ ] Danger zone: `CustomEvent` dispatched regardless of whether global function exists
- [ ] Product sub-carousel arrows navigate between products
- [ ] Desktop: 3 visible slides with center focus
- [ ] Mobile: 1 visible slide
- [ ] Touch swipe works on mobile
