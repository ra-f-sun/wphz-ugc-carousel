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
 * 5. INITIAL POSITIONING: Double RAF replaced with ResizeObserver.
 *    ResizeObserver fires after the browser has committed actual pixel
 *    dimensions — no timing guesswork. It also replaces the window resize
 *    listener, handling both initial layout and subsequent resizes in one place.
 *    FIX → Both carousels now use ResizeObserver on their container element.
 *
 * 6. LIVE DRAG PREVIEW: `_onDragMove` now moves the track in real-time as the
 *    user drags, matching native swipe feel.
 *
 * 7. PRODUCT CAROUSEL INFINITE: WPHZProductCarousel now uses the same
 *    clone-based infinite loop pattern as the main carousel. Pixel-based
 *    transforms replace the old percentage approach. Single-item carousels
 *    (only one product) skip cloning and hide arrows entirely.
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
    this.defaultMuted = el.dataset.muted === "1";
    this.muteByItemIndex = {};
    this.direction = el.dataset.direction || "ltr";

    // Cached slide width in px (set by _setSlideSizes)
    this._slideWidth = 0;

    // Drag/Swipe state
    this._drag = { active: false, startX: 0, startY: 0, diffX: 0, diffY: 0 };
    this._snapTimer = null;

    // ResizeObserver instance — stored so it could be disconnected if needed
    this._resizeObserver = null;

    this.posterEngine =
      typeof window.WPHZUGCPosterEngine === "function"
        ? new window.WPHZUGCPosterEngine(el)
        : null;

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

    // ResizeObserver replaces both the double-RAF init measurement and the
    // window resize listener. It fires after the browser has committed real
    // pixel dimensions to the observed element, so offsetWidth is always
    // accurate — no timing guesswork.
    //
    // First observation fires on attach (replaces double-RAF).
    // Subsequent observations fire on resize (replaces window listener).
    // The visibility gate ensures _playCenter() only runs once on first layout.
    this._resizeObserver = new ResizeObserver(() => {
      this._setSlideSizes();
      this._applyTransform(false);

      if (this.track.style.visibility === "hidden") {
        this.track.style.visibility = "";
        this._playCenter();
      }
    });

    this._resizeObserver.observe(this.stage);
  }

  /* ── Slide Sizing ────────────────────────────────────────────────────────
   * Sets each slide's flex-basis as an absolute pixel value derived from
   * the stage's measured width. Inline styles override any CSS media-query
   * rules, so no changes to the stylesheet are required.
   * Called by ResizeObserver on every layout change.
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

  /* ── Phase 12: Dual-Resolution Source Selection ─────────────────────
   * Reads data attributes explicitly set by PHP and determines the maximum
   * safe resolution target based on live client APIs.
   * Runs before DOM cloning so copies inherit the selected source metadata.
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

      video.dataset.selectedSrc = targetSrc;

      this.posterEngine?.applyPoster(video);

      // No explicit poster URL: attach lightweight metadata source so browser
      // can render first-frame fallback instead of black inactive slides.
      const hasPoster = !!(video.dataset.posterUrl || "").trim();
      if (!hasPoster && this.posterEngine) {
        this.posterEngine.attachSource(video);
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

    this.track.style.transition = animate ? "" : "none";
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
   */
  _scheduleSnapback() {
    clearTimeout(this._snapTimer);

    this._snapTimer = setTimeout(() => {
      const lo = this.cloneCount; // N
      const hi = this.cloneCount + this.totalOrig; // 2N

      if (this.current >= lo && this.current < hi) return;

      const prevCurrent = this.current;

      if (this.current >= hi) {
        this.current -= this.totalOrig;
      } else {
        this.current += this.totalOrig;
      }

      // Pause the clone video and resume on the real slide
      const cloneVideo =
        this.slides[prevCurrent]?.querySelector(".wphz-ugc-video");
      if (cloneVideo) {
        if (this.posterEngine) {
          this.posterEngine.pause(cloneVideo);
        } else {
          cloneVideo.pause();
          cloneVideo.currentTime = 0;
        }
      }

      this._applyTransform(false);
      this._updateSlideClasses();
      this._playCenter();
    }, 550); // 500 ms transition + 50 ms buffer
  }

  /* ── Slide Classes ─────────────────────────────────────────────────────── */
  _updateSlideClasses() {
    this.slides.forEach((slide, i) => {
      slide.classList.toggle("wphz-ugc-slide--active", i === this.current);
    });
  }

  /* ── Video Control ─────────────────────────────────────────────────────── */
  _playCenter() {
    const slide = this.slides[this.current];
    const video = slide?.querySelector(".wphz-ugc-video");
    if (!video) return;

    this._muteAllNonActiveSlides();

    const itemMuted = this._isItemMutedBySlide(slide);

    const playPromise = this.posterEngine
      ? this.posterEngine.play(video, itemMuted)
      : (() => {
          video.muted = itemMuted;
          return video.play();
        })();

    if (playPromise && typeof playPromise.catch === "function") {
      playPromise.catch(() => {
        // Unmuted autoplay blocked — retry muted (browsers always allow this)
        const itemIndex = this._getItemIndexFromSlide(slide);
        this._setItemMuted(itemIndex, true);
        video.muted = true;
        const retryPromise = this.posterEngine
          ? this.posterEngine.play(video, true)
          : video.play();
        retryPromise?.catch(() => {
          // Still blocked (tab not yet focused) — give up silently
        });
      });
    }

    video.onended = () => this._onVideoEnded();
  }

  _pauseCenter() {
    const slide = this.slides[this.current];
    const video = slide?.querySelector(".wphz-ugc-video");
    if (video) {
      const itemIndex = this._getItemIndexFromSlide(slide);
      this._setItemMuted(itemIndex, true);

      if (this.posterEngine) {
        this.posterEngine.pause(video);
      } else {
        video.pause();
        video.currentTime = 0;
      }
    }
  }

  _onVideoEnded() {
    this.direction === "rtl" ? this.prev() : this.next();
  }

  /* ── Drag & Swipe ──────────────────────────────────────────────────────── */
  _bindDrag() {
    const track = this.track;

    track.addEventListener("mousedown", (e) => this._onDragStart(e.clientX, e.clientY));
    window.addEventListener("mousemove", (e) => this._onDragMove(e.clientX, e.clientY));
    window.addEventListener("mouseup", () => this._onDragEnd());

    track.addEventListener(
      "touchstart",
      (e) => this._onDragStart(e.touches[0].clientX, e.touches[0].clientY),
      { passive: true },
    );
    track.addEventListener(
      "touchmove",
      (e) => this._onDragMove(e.touches[0].clientX, e.touches[0].clientY),
      { passive: true },
    );
    track.addEventListener("touchend", () => this._onDragEnd());
  }

  _onDragStart(x, y) {
    this._drag.active = true;
    this._drag.startX = x;
    this._drag.startY = y;
    this._drag.diffX = 0;
    this._drag.diffY = 0;
  }

  _onDragMove(x, y) {
    if (!this._drag.active || !this._slideWidth) return;

    this._drag.diffX = x - this._drag.startX;
    this._drag.diffY = y - this._drag.startY;
  }

  _onDragEnd() {
    if (!this._drag.active) return;
    this._drag.active = false;

    const threshold = 12;
    const absX = Math.abs(this._drag.diffX);
    const absY = Math.abs(this._drag.diffY);
    const isHorizontalSwipe = absX > absY;

    if (!isHorizontalSwipe) {
      return;
    }

    if (this._drag.diffX < -threshold) {
      this.next();
    } else if (this._drag.diffX > threshold) {
      this.prev();
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

      const itemIndex = this._getItemIndexFromSlide(slide);
      if (itemIndex < 0) return;

      const clickedIndex = this.slides.indexOf(slide);
      const nextMuted = !this._isItemMutedBySlide(slide);
      this._setItemMuted(itemIndex, nextMuted);

      if (clickedIndex !== -1 && clickedIndex !== this.current) {
        this.goTo(clickedIndex);
        return;
      }

      video.muted = nextMuted;
    });
  }

  _setItemMuted(itemIndex, isMuted) {
    if (itemIndex < 0) return;

    this.muteByItemIndex[itemIndex] = !!isMuted;

    this.slides.forEach((slide) => {
      if (this._getItemIndexFromSlide(slide) !== itemIndex) return;
      this._setSlideMuted(slide, !!isMuted);
    });
  }

  _muteAllNonActiveSlides() {
    this.slides.forEach((slide, index) => {
      if (index === this.current) return;
      this._setSlideMuted(slide, true);
    });
  }

  _setSlideMuted(slide, isMuted) {
    if (!slide) return;

    const video = slide.querySelector(".wphz-ugc-video");
    if (video) {
      video.muted = !!isMuted;
    }

    const btn = slide.querySelector(".wphz-ugc-mute-btn");
    if (!btn) return;

    const muteIcon = btn.querySelector(".wphz-icon-mute");
    const unmuteIcon = btn.querySelector(".wphz-icon-unmute");
    if (muteIcon) muteIcon.style.display = isMuted ? "" : "none";
    if (unmuteIcon) unmuteIcon.style.display = isMuted ? "none" : "";
  }

  _isItemMutedBySlide(slide) {
    const itemIndex = this._getItemIndexFromSlide(slide);
    if (itemIndex < 0) return this.defaultMuted;
    if (Object.prototype.hasOwnProperty.call(this.muteByItemIndex, itemIndex)) {
      return !!this.muteByItemIndex[itemIndex];
    }
    return this.defaultMuted;
  }

  _getItemIndexFromSlide(slide) {
    if (!slide) return -1;
    const raw = slide.dataset.index;
    const parsed = Number.parseInt(raw || "", 10);
    return Number.isNaN(parsed) ? -1 : parsed;
  }
}

/* ══════════════════════════════════════════════════════════════════════════ */

class WPHZProductCarousel {
  constructor(el) {
    this.wrap = el;
    this.track = el.querySelector(".wphz-ugc-products-track");
    this.origItems = Array.from(el.querySelectorAll(".wphz-ugc-product-item"));
    this.totalOrig = this.origItems.length;

    this._itemWidth = 0;
    this._snapTimer = null;
    this._resizeObserver = null;

    if (this.totalOrig === 0) return;

    // Single item — no carousel behaviour needed, hide arrows and exit.
    if (this.totalOrig === 1) {
      this._setArrowVisibility(false);
      return;
    }

    // Remove gap — pixel-based step math requires items to be flush.
    // The card padding/border provides enough visual separation.
    this.track.style.gap = "0";

    this._buildInfiniteTrack();
    this._bindArrows();

    // ResizeObserver replaces both the single-RAF init and the window resize
    // listener. Fires after real pixel dimensions are committed by the browser.
    this._resizeObserver = new ResizeObserver(() => {
      this._setItemSizes();
      this._applyTransform(false);
    });

    this._resizeObserver.observe(this.wrap);
  }

  /* ── Infinite Track ──────────────────────────────────────────────────────
   * Same clone-before/after pattern as WPHZUGCCarousel.
   * Index map after cloning (N = totalOrig):
   *   0   … N-1   → prepended clones
   *   N   … 2N-1  → original items   ← current starts here
   *   2N  … 3N-1  → appended clones
   */
  _buildInfiniteTrack() {
    this.cloneCount = this.totalOrig;

    for (let i = this.totalOrig - 1; i >= 0; i--) {
      const clone = this.origItems[i].cloneNode(true);
      clone.setAttribute("aria-hidden", "true");
      this.track.insertBefore(clone, this.track.firstChild);
    }

    for (let i = 0; i < this.totalOrig; i++) {
      const clone = this.origItems[i].cloneNode(true);
      clone.setAttribute("aria-hidden", "true");
      this.track.appendChild(clone);
    }

    this.items = Array.from(
      this.track.querySelectorAll(".wphz-ugc-product-item"),
    );
    this.totalItems = this.items.length;

    // Start at the first original item (index N)
    this.current = this.cloneCount;
  }

  /* ── Item Sizing ─────────────────────────────────────────────────────────
   * Each item fills exactly the wrap width — one item visible at a time.
   * Pixel-based so translateX step = exactly one item = wrap.offsetWidth.
   */
  _setItemSizes() {
    this._itemWidth = this.wrap.offsetWidth;
    if (!this._itemWidth) return;

    this.items.forEach((item) => {
      item.style.flex = `0 0 ${this._itemWidth}px`;
    });
  }

  /* ── Transform ───────────────────────────────────────────────────────────
   * translateX = -(current × itemWidth)
   * current=N (first original) → track shifts left by N item-widths,
   * placing the first original flush at position 0 in the viewport.
   */
  _applyTransform(animate) {
    if (!this._itemWidth) return;

    const translateX = -(this.current * this._itemWidth);

    this.track.style.transition = animate ? "" : "none";
    this.track.style.transform = `translateX(${translateX}px)`;

    if (!animate) {
      void this.track.offsetHeight; // Force reflow → instant jump, no flash
      this.track.style.transition = "";
    }
  }

  /* ── Navigation ──────────────────────────────────────────────────────────*/
  _slide(dir) {
    this.current += dir;
    this._applyTransform(true);
    this._scheduleSnapback();
  }

  /* ── Snapback ────────────────────────────────────────────────────────────
   * After the 300 ms CSS transition + 50 ms buffer, jump silently from a
   * clone back to the corresponding original without animation.
   */
  _scheduleSnapback() {
    clearTimeout(this._snapTimer);

    this._snapTimer = setTimeout(() => {
      const lo = this.cloneCount;
      const hi = this.cloneCount + this.totalOrig;

      if (this.current >= lo && this.current < hi) return;

      if (this.current >= hi) {
        this.current -= this.totalOrig;
      } else {
        this.current += this.totalOrig;
      }

      this._applyTransform(false);
    }, 350); // 300 ms transition + 50 ms buffer
  }

  /* ── Arrows ──────────────────────────────────────────────────────────────*/
  _bindArrows() {
    this.wrap
      .querySelector(".wphz-product-arrow--next")
      ?.addEventListener("click", () => this._slide(1));
    this.wrap
      .querySelector(".wphz-product-arrow--prev")
      ?.addEventListener("click", () => this._slide(-1));
  }

  _setArrowVisibility(visible) {
    const display = visible ? "" : "none";
    this.wrap
      .querySelector(".wphz-product-arrow--next")
      ?.style.setProperty("display", display);
    this.wrap
      .querySelector(".wphz-product-arrow--prev")
      ?.style.setProperty("display", display);
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

/* ── External Button Binding (auto, no functions.php needed) ─────────────────
 * Any element with [data-wphz-target] + [data-wphz-action] anywhere on the
 * page will control the matching carousel instance automatically.
 *
 * Markup:
 *   <button data-wphz-target="42" data-wphz-action="next">→</button>
 *   <button data-wphz-target="42" data-wphz-action="prev">←</button>
 *
 * - data-wphz-target : must match the carousel's data-carousel-id value
 * - data-wphz-action : "next" or "prev"
 *
 * Uses event delegation on document so buttons added dynamically (e.g. via
 * page builders or AJAX) also work without re-binding.
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
