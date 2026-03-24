/**
 * WPHZ UGC Carousel — Frontend JavaScript Engine
 * Infinite center-play carousel with clone-based looping.
 * Phase 7: Video carousel, product sub-carousel, drag/swipe, danger events
 * Phase 8: WooCommerce AJAX Add-to-Cart integration
 *
 * FIX NOTES (vs previous version):
 * ─────────────────────────────────────────────────────────────────────────────
 * 1. LAYOUT ROOT CAUSE: The old code used `translateX(-pct%)` where `pct` was
 *    computed as `(offset / totalSlides) * 100`. CSS `translateX` percentages
 *    are relative to the element's OWN BOX width (= stage width), NOT its
 *    scrollable content width (= totalSlides × slideWidth). With 15 slides the
 *    content is 3.33× wider than the box, so every translation was 3.33× too
 *    short — slides appeared glued together and the "hard end" was hit after
 *    only ~1.5 clicks instead of after all originals were exhausted.
 *    FIX → All transforms are now pixel-based: translateX = -(current − center) × slideWidth.
 *
 * 2. SLIDE SIZING ROOT CAUSE: `flex: 0 0 calc(100% / 4.5)` inside a flex
 *    container with no explicit width creates a circular dependency — browsers
 *    resolve `100%` against the container's own content size, which itself
 *    depends on the children, yielding unpredictable widths.
 *    FIX → `_setSlideSizes()` measures the stage's pixel width and sets each
 *    slide's flex-basis explicitly in pixels via inline style (inline styles
 *    beat media-query rules, so no CSS changes are required).
 *
 * 3. SNAPBACK VIDEO HANDOFF: After the infinite snapback jump (clone → original)
 *    the clone's video kept playing off-screen while the visible original's
 *    video never started.
 *    FIX → `_scheduleSnapback()` now pauses the clone video and calls
 *    `_playCenter()` on the newly-current original slide.
 *
 * 4. AUTOPLAY FALLBACK: When unmuted autoplay is blocked by browser policy the
 *    old code silently gave up. Now it retries with `video.muted = true`.
 *
 * 5. INITIAL POSITIONING: One `requestAnimationFrame` is not always enough for
 *    the browser to compute the flex layout after DOM mutations (cloning).
 *    FIX → Double RAF so slide sizes are read after at least one layout pass.
 *
 * 6. LIVE DRAG PREVIEW: `_onDragMove` now moves the track in real-time as the
 *    user drags, matching native swipe feel.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class WPHZUGCCarousel {
  constructor(el) {
    this.root = el;
    this.id = el.dataset.carouselId;
    this.stage = el.querySelector(".wphz-ugc-stage");
    this.track = el.querySelector(".wphz-ugc-track");
    this.origSlides = Array.from(el.querySelectorAll(".wphz-ugc-slide"));
    this.totalOrig = this.origSlides.length;
    this.isMuted = el.dataset.muted === "1";
    this.direction = el.dataset.direction || "ltr";

    // Cached slide width in px (set by _setSlideSizes)
    this._slideWidth = 0;

    // Drag/Swipe state
    this._drag = { active: false, startX: 0, diffX: 0 };
    this._snapTimer = null;

    this.init();
  }

  init() {
    if (this.totalOrig === 0) return;

    this._setVideoSources(); // Phase 12 Governor
    this._buildInfiniteTrack();
    this._bindDrag();
    this._bindMuteButtons();

    // Start with the first original slide in the active position
    this.current = this.cloneCount;
    this._updateSlideClasses();

    // Hide track until positioned — prevents flash at wrong position
    this.track.style.visibility = "hidden";

    // Double RAF: first waits for paint, second ensures the flex layout
    // triggered by _buildInfiniteTrack() is fully computed before we read widths.
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        this._setSlideSizes();
        this._applyTransform(false);
        this.track.style.visibility = "";
        this._playCenter();
      });
    });

    // Recompute on resize (slide sizes AND transform must both update)
    window.addEventListener("resize", () => {
      this._setSlideSizes();
      this._applyTransform(false);
    });
  }

  /* ── Slide Sizing ────────────────────────────────────────────────────────
   * Sets each slide's flex-basis as an absolute pixel value derived from
   * the stage's measured width. Inline styles override any CSS media-query
   * rules, so no changes to the stylesheet are required.
   * Called on init (double-RAF) and on every resize.
   */
  _setSlideSizes() {
    const stageWidth = this.stage.offsetWidth;
    if (!stageWidth) return; // Guard: skip if carousel is in a hidden container

    const visible = this._getVisibleCount();
    this._slideWidth = stageWidth / visible;

    this.slides.forEach((slide) => {
      slide.style.flex = `0 0 ${this._slideWidth}px`;
    });
  }

  /* ── Infinite Track: clone all slides before & after originals ────────── */
  _buildInfiniteTrack() {
    this.cloneCount = this.totalOrig;

    // Prepend clones — inserting in reverse order before firstChild produces
    // a forward-ordered block at the front of the track.
    for (let i = this.totalOrig - 1; i >= 0; i--) {
      const clone = this.origSlides[i].cloneNode(true);
      clone.setAttribute("aria-hidden", "true");
      this.track.insertBefore(clone, this.track.firstChild);
    }

    // Append clones
    for (let i = 0; i < this.totalOrig; i++) {
      const clone = this.origSlides[i].cloneNode(true);
      clone.setAttribute("aria-hidden", "true");
      this.track.appendChild(clone);
    }

    // Refresh slides array
    // Track layout after cloning:
    //   indices  0 … N-1         → prepended clones  (mirror of originals)
    //   indices  N … 2N-1        → original slides
    //   indices  2N … 3N-1       → appended clones   (mirror of originals)
    this.slides = Array.from(this.track.querySelectorAll(".wphz-ugc-slide"));
    this.totalSlides = this.slides.length;
  }

  /* ── Phase 12: Dual-Resolution Video Governor ───────────────────────
   * Reads data attributes explicitly set by PHP and determines the maximum
   * safe resolution target based on live client APIs.
   * Runs natively before DOM cloning so copies inherit the safe URL natively.
   */
  _setVideoSources() {
    const isSlow = navigator.connection && navigator.connection.downlink < 3;
    const isMobile = window.innerWidth <= 768;
    const preferSD = isSlow || isMobile;

    this.origSlides.forEach((slide) => {
      const video = slide.querySelector(".wphz-ugc-video");
      if (!video) return;

      const hd = video.dataset.srcHd;
      const sd = video.dataset.srcSd;

      let targetSrc = "";

      // Graceful Failover Policy
      if (hd && !sd) {
        targetSrc = hd;
      } else if (sd && !hd) {
        targetSrc = sd;
      } else if (hd && sd) {
        targetSrc = preferSD ? sd : hd;
      }

      if (targetSrc) {
        video.src = targetSrc;
      }
    });
  }

  /* ── Navigation ─────────────────────────────────────────────────────────── */
  goTo(index) {
    this._pauseCenter();
    this.current = index;
    this._updateSlideClasses();
    this._applyTransform(true);
    this._playCenter();
    this._scheduleSnapback();
  }

  next() {
    this.goTo(this.current + 1);
  }
  prev() {
    this.goTo(this.current - 1);
  }

  /* ── Transform (pixel-based, center-offset positioning) ─────────────────
   *
   * Places this.current at the visual position defined by _getCenterOffset().
   *
   * Why pixels?
   *   CSS translateX(%) is relative to the element's OWN box width. The track's
   *   box width equals the stage width (it's a full-width block child), but its
   *   CONTENT is (totalSlides × slideWidth) wide — up to 3× the box. Using a
   *   percentage of the box therefore undershoots every translation by that
   *   same factor, causing the "hard end" behaviour the user reported.
   *   Pixels sidestep this entirely.
   *
   * Formula:
   *   translateX = -(current − centerOffset) × slideWidth
   *
   *   centerOffset is the visual slot (0 = left edge) where the active slide
   *   should appear in the viewport. See _getCenterOffset().
   */
  _applyTransform(animate) {
    if (!this._slideWidth) return; // Sizes not yet computed — skip

    const visible = this._getVisibleCount();
    const center = this._getCenterOffset(visible);
    const translateX = -(this.current - center) * this._slideWidth;

    if (!animate) {
      this.track.style.transition = "none";
    } else {
      this.track.style.transition = ""; // Restore CSS transition
    }

    this.track.style.transform = `translateX(${translateX}px)`;

    if (!animate) {
      void this.track.offsetHeight; // Force reflow → instant snap, no flash
      this.track.style.transition = "";
    }
  }

  _getVisibleCount() {
    if (window.innerWidth <= 768) return 1.7;
    if (window.innerWidth <= 1024) return 3.3;
    return 4.0;
  }

  /* ── Center Offset ───────────────────────────────────────────────────────
   * Returns the visual slot number (0-indexed from left) where the ACTIVE
   * slide should sit inside the visible viewport.
   *
   * Formula: center = visible − 1.25
   *
   * Desktop (4.5 visible):  center = 3.25
   *   → ~0.25 slide peeks on left | 4 full slides | ~0.25 peek on right
   *   → active = last fully-visible slide ("the one before the right peek")
   *
   * Tablet (3.3 visible):   center ≈ 2.05
   *   → tiny left peek | 2 full slides | active | right peek
   *
   * Mobile (1.33 visible):  center ≈ 0.08
   *   → active (mostly full) | 0.33 peek on right
   *
   * Math check (desktop): window = [current−3.25 … current+1.25]
   *   • slide current−4: 0.25 visible on left ✓ (partial left peek)
   *   • slides current−3, −2, −1, current: 1.0 each (fully visible)
   *   • slide current+1: 0.25 visible on right ✓ (partial right peek)
   *   • total = 0.25 + 4 + 0.25 = 4.5 ✓
   */
  _getCenterOffset(visible) {
    if (window.innerWidth <= 768) return (visible - 1) / 2;
    return Math.max(0, visible - 2.25);
    // desktop: 4.0 - 2.25 = 1.75 → left=75%, right=25% ✓
    // tablet:  3.3 - 2.25 = 1.05 → left=5%,  right=25%
  }
  /* ── Infinite Snapback ───────────────────────────────────────────────────
   * After the CSS transition completes (~400 ms + 50 ms buffer), if we have
   * scrolled into a clone zone, silently jump to the corresponding real slide
   * without animation — the clone and the original look identical so the user
   * sees no discontinuity.
   *
   * Index map (N = totalOrig):
   *   Prepended clones : 0   … N-1       (clone of orig 0…N-1)
   *   Originals        : N   … 2N-1
   *   Appended clones  : 2N  … 3N-1      (clone of orig 0…N-1)
   *
   * Bug fix: also hands off video playback from the clone to the original so
   * the video does not keep running off-screen after the jump.
   */
  _scheduleSnapback() {
    clearTimeout(this._snapTimer);

    this._snapTimer = setTimeout(() => {
      const lo = this.cloneCount; // N
      const hi = this.cloneCount + this.totalOrig; // 2N

      // Nothing to do if we are still in the originals zone
      if (this.current >= lo && this.current < hi) return;

      const prevCurrent = this.current;

      if (this.current >= hi) {
        this.current -= this.totalOrig;
      } else {
        this.current += this.totalOrig;
      }

      // Pause the clone video (now scrolled off-screen) and start the
      // corresponding original's video so playback continues seamlessly.
      const cloneVideo =
        this.slides[prevCurrent]?.querySelector(".wphz-ugc-video");
      if (cloneVideo) {
        cloneVideo.pause();
        cloneVideo.currentTime = 0;
      }

      this._applyTransform(false); // Instant, invisible position jump
      this._updateSlideClasses();
      this._playCenter(); // Resume on the real slide
    }, 450); // 400 ms transition + 50 ms buffer
  }

  /* ── Slide Classes ─────────────────────────────────────────────────────── */
  _updateSlideClasses() {
    this.slides.forEach((slide, i) => {
      slide.classList.toggle("wphz-ugc-slide--active", i === this.current);
    });
  }

  /* ── Video Control ─────────────────────────────────────────────────────── */
  _playCenter() {
    const video = this.slides[this.current]?.querySelector(".wphz-ugc-video");
    if (!video) return;

    video.muted = this.isMuted;

    const playPromise = video.play();
    if (playPromise !== undefined) {
      playPromise.catch(() => {
        // Unmuted autoplay was blocked — fall back to muted autoplay,
        // which browsers universally allow.
        video.muted = true;
        this.isMuted = true;
        video.play().catch(() => {
          // Still blocked (e.g. tab not yet focused) — give up silently.
        });
      });
    }

    video.onended = () => this._onVideoEnded();
  }

  _pauseCenter() {
    const video = this.slides[this.current]?.querySelector(".wphz-ugc-video");
    if (video) {
      video.pause();
      video.currentTime = 0;
    }
  }

  _onVideoEnded() {
    this.direction === "rtl" ? this.prev() : this.next();
  }

  /* ── Drag & Swipe ──────────────────────────────────────────────────────── */
  _bindDrag() {
    const track = this.track;

    track.addEventListener("mousedown", (e) => this._onDragStart(e.clientX));
    window.addEventListener("mousemove", (e) => this._onDragMove(e.clientX));
    window.addEventListener("mouseup", () => this._onDragEnd());

    track.addEventListener(
      "touchstart",
      (e) => this._onDragStart(e.touches[0].clientX),
      { passive: true },
    );
    track.addEventListener(
      "touchmove",
      (e) => this._onDragMove(e.touches[0].clientX),
      { passive: true },
    );
    track.addEventListener("touchend", () => this._onDragEnd());
  }

  _onDragStart(x) {
    this._drag.active = true;
    this._drag.startX = x;
    this._drag.diffX = 0;
    this.track.style.transition = "none"; // Disable transition during drag
  }

  _onDragMove(x) {
    if (!this._drag.active || !this._slideWidth) return;

    this._drag.diffX = x - this._drag.startX;

    // Live drag preview: shift the track in real-time with the finger/cursor.
    const visible = this._getVisibleCount();
    const center = this._getCenterOffset(visible);
    const base = -(this.current - center) * this._slideWidth;
    this.track.style.transform = `translateX(${base + this._drag.diffX}px)`;
  }

  _onDragEnd() {
    if (!this._drag.active) return;
    this._drag.active = false;
    this.track.style.transition = ""; // Re-enable CSS transition

    const threshold = 60; // px required to trigger a slide change

    if (this._drag.diffX < -threshold) {
      this.next();
    } else if (this._drag.diffX > threshold) {
      this.prev();
    } else {
      this._applyTransform(true); // Snap back to current slide
    }
  }

  /* ── Mute Toggles ──────────────────────────────────────────────────────── */
  _bindMuteButtons() {
    this.root.addEventListener("click", (e) => {
      const btn = e.target.closest(".wphz-ugc-mute-btn");
      if (!btn) return;

      const slide = btn.closest(".wphz-ugc-slide");
      const video = slide?.querySelector(".wphz-ugc-video");
      if (!video) return;

      video.muted = !video.muted;
      this.isMuted = video.muted; // Update global state

      const muteIcon = btn.querySelector(".wphz-icon-mute");
      const unmuteIcon = btn.querySelector(".wphz-icon-unmute");
      if (muteIcon) muteIcon.style.display = video.muted ? "" : "none";
      if (unmuteIcon) unmuteIcon.style.display = video.muted ? "none" : "";
    });
  }
}

/* ══════════════════════════════════════════════════════════════════════════ */

class WPHZProductCarousel {
  constructor(el) {
    this.wrap = el;
    this.track = el.querySelector(".wphz-ugc-products-track");
    this.items = Array.from(el.querySelectorAll(".wphz-ugc-product-item"));
    this.current = 0;

    if (this.items.length > 1) {
      this._bindArrows();
    }
  }

  _bindArrows() {
    this.wrap
      .querySelector(".wphz-product-arrow--next")
      ?.addEventListener("click", () => this._slide(1));
    this.wrap
      .querySelector(".wphz-product-arrow--prev")
      ?.addEventListener("click", () => this._slide(-1));
  }

  _slide(dir) {
    const max = this.items.length - 1;
    this.current = Math.max(0, Math.min(max, this.current + dir));
    this.track.style.transform = `translateX(-${this.current * 100}%)`;
  }
}

/* ── Boot Process & Global API ────────────────────────────────────────────── */
window.wphzUGCFrontend = window.wphzUGCFrontend || {};
window.wphzUGCFrontend.instances = {};

document.addEventListener("DOMContentLoaded", () => {
  // 1. Boot main carousels (clones slides for infinite loop)
  document.querySelectorAll(".wphz-ugc-carousel").forEach((el) => {
    const id = el.dataset.carouselId;
    const instance = new WPHZUGCCarousel(el);
    if (id) {
      window.wphzUGCFrontend.instances[id] = instance;
    }
  });

  // 2. Boot product sub-carousels (runs after clones exist)
  document
    .querySelectorAll(".wphz-ugc-products--carousel")
    .forEach((el) => new WPHZProductCarousel(el));
});

/* ── Custom Event Listeners for External Controls ────────────────────────── */
/* ── External Button Binding (auto, no functions.php needed) ─────────────────
 * Any element with [data-wphz-target] + [data-wphz-action] anywhere on the
 * page will control the matching carousel instance automatically.
 *
 * Markup:
 *   <button data-wphz-target="42" data-wphz-action="next">→</button>
 *   <button data-wphz-target="42" data-wphz-action="prev">←</button>
 *
 * - data-wphz-target  : must match the carousel's data-carousel-id value
 * - data-wphz-action  : "next" or "prev"
 *
 * Uses event delegation on document so buttons added dynamically (e.g. via
 * page builders or AJAX) also work without re-binding.
 *
 * The old custom-event approach (wphz_carousel_slide_next/prev) and the
 * functions.php snippet are no longer needed and can be deleted.
 */
document.addEventListener("click", (e) => {
  const btn = e.target.closest("[data-wphz-target]");
  if (!btn) return;

  const id = btn.dataset.wphzTarget;
  const action = btn.dataset.wphzAction;
  const instance = window.wphzUGCFrontend?.instances?.[id];

  if (!instance) {
    console.warn(`WPHZ Carousel: no instance found for id "${id}"`);
    return;
  }

  if (action === "next") instance.next();
  else if (action === "prev") instance.prev();
});

/* ── Phase 8: Add to Cart Integration ──────────────────────────────────────── */
document.addEventListener("click", (e) => {
  const btn = e.target.closest(".wphz-ugc-atc-btn");
  if (!btn) return;

  if (!window.wphzUGCFrontend) {
    console.error(
      "WPHZ UGC Carousel: wphzUGCFrontend localized object is missing.",
    );
    return;
  }

  const productId = btn.dataset.productId;
  const nonce = wphzUGCFrontend.nonce;
  const original = btn.textContent;

  btn.textContent = "...";
  btn.disabled = true;
  btn.classList.add("loading");

  const body = new URLSearchParams({
    action: "wphz_ugc_add_to_cart",
    product_id: productId,
    quantity: 1,
    nonce: nonce,
  });

  fetch(wphzUGCFrontend.ajaxurl, { method: "POST", body })
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        btn.textContent = wphzUGCFrontend.i18n.added;
        // Trigger WC fragment refresh via jQuery (WooCommerce & CheckoutWC
        // listen for jQuery events, not vanilla CustomEvents).
        if (window.jQuery) {
          jQuery(document.body).trigger("wc_fragment_refresh");
          jQuery(document.body).trigger("added_to_cart", [
            data.data?.fragments,
            data.data?.cart_hash,
          ]);
        }
      } else {
        console.error("WPHZ ATC Error:", data);
        btn.textContent = wphzUGCFrontend.i18n.error;
      }
    })
    .catch((error) => {
      console.error("WPHZ ATC Network Error:", error);
      btn.textContent = wphzUGCFrontend.i18n.error;
    })
    .finally(() => {
      btn.classList.remove("loading");
      setTimeout(() => {
        if (btn) {
          btn.textContent = original;
          btn.disabled = false;
        }
      }, 2500);
    });
});
