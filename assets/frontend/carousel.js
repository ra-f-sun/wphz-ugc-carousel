/**
 * WPHZ UGC Carousel — Frontend JavaScript Engine
 * Infinite center-play carousel with clone-based looping.
 * Phase 7: Video carousel, product sub-carousel, drag/swipe, danger events
 * Phase 8: WooCommerce AJAX Add-to-Cart integration
 *
 * ARCHITECTURE
 * ─────────────────────────────────────────────────────────────────────────────
 * Track layout after cloning (N = totalOrig):
 *
 *   indices  0 … N-1    → prepended clones
 *   indices  N … 2N-1   → original slides
 *   indices  2N … 3N-1  → appended clones
 *
 * VIDEO LOADING STRATEGY (inspired by Tolstoy):
 *   Every <video> gets src set at init with preload="none".
 *   preload="none" = zero network requests, identical page load cost to no src.
 *   Since src is set before cloning, clones inherit it automatically.
 *   When play() is called, the browser fetches on demand — no black flash,
 *   no attachSource → load() step, no poster rewrite artifacts.
 *
 * INITIAL POSITION:
 *   current = cloneCount + Math.round(centerOffset)
 *   All visible positions filled with originals. Clone boundary pushed
 *   N slides away — normal navigation never reaches it.
 *
 * VIDEO STATE RULES:
 *   - All slides (originals + clones): src always set, preload="none".
 *   - Active slide: play() called — browser fetches and plays.
 *   - Inactive originals: paused in place, native paused frame preserved.
 *   - Distant videos: reset via circular index-distance calculation.
 *   - Clone posters: synced via canvas capture before navigation.
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
    this.isMuted = this.defaultMuted;
    this.direction = el.dataset.direction || "ltr";

    this._slideWidth = 0;
    this._drag = { active: false, startX: 0, startY: 0, diffX: 0, diffY: 0 };
    this._snapTimer = null;
    this._resizeObserver = null;
    this._viewportObserver = null;
    this._inViewport = false;  // stays false until IntersectionObserver confirms visibility

    this.posterEngine =
      typeof window.WPHZUGCPosterEngine === "function"
        ? new window.WPHZUGCPosterEngine(el)
        : null;

    this.init();
  }

  /* ═══════════════════════════════════════════════════════════════════════
   *  INIT
   * ═══════════════════════════════════════════════════════════════════════ */

  init() {
    if (this.totalOrig === 0) return;

    this._setVideoSources();   // Select resolution + set src + preload="none"
    this._buildInfiniteTrack(); // Clone AFTER src is set — clones inherit it
    this._bindDrag();
    this._bindMuteButtons();

    // Shifted start — originals fill the entire viewport
    const visible = this._getVisibleCount();
    const centerOffset = this._getCenterOffset(visible);
    this.current = this.cloneCount + Math.round(centerOffset);
    this._updateSlideClasses();

    // Hide track until first measurement
    this.track.style.visibility = "hidden";

    this._resizeObserver = new ResizeObserver(() => {
      this._setSlideSizes();
      this._applyTransform(false);

      if (this.track.style.visibility === "hidden") {
        this.track.style.visibility = "";
        // Only autoplay if the carousel is already in the viewport.
        // If not, IntersectionObserver will trigger _resumeForViewport() when it scrolls in.
        if (this._inViewport) this._playCenter();
      }
    });
    this._resizeObserver.observe(this.stage);

    // Viewport observer — pause/resume when carousel section scrolls in/out
    this._viewportObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          this._inViewport = entry.isIntersecting;
          if (entry.isIntersecting) {
            this._resumeForViewport();
          } else {
            this._pauseForViewport();
          }
        });
      },
      { threshold: 0.2 },
    );
    this._viewportObserver.observe(this.root);
  }

  /* ═══════════════════════════════════════════════════════════════════════
   *  SLIDE SIZING
   * ═══════════════════════════════════════════════════════════════════════ */

  _setSlideSizes() {
    const stageWidth = this.stage.offsetWidth;
    if (!stageWidth) return;

    const visible = this._getVisibleCount();
    this._slideWidth = stageWidth / visible;

    this.slides.forEach((slide) => {
      slide.style.flex = `0 0 ${this._slideWidth}px`;
    });
  }

  /* ═══════════════════════════════════════════════════════════════════════
   *  INFINITE TRACK — clone all slides before & after originals
   * ═══════════════════════════════════════════════════════════════════════ */

  _buildInfiniteTrack() {
    this.cloneCount = this.totalOrig;

    for (let i = this.totalOrig - 1; i >= 0; i--) {
      const clone = this.origSlides[i].cloneNode(true);
      clone.setAttribute("aria-hidden", "true");
      this.track.insertBefore(clone, this.track.firstChild);
    }

    for (let i = 0; i < this.totalOrig; i++) {
      const clone = this.origSlides[i].cloneNode(true);
      clone.setAttribute("aria-hidden", "true");
      this.track.appendChild(clone);
    }

    this.slides = Array.from(this.track.querySelectorAll(".wphz-ugc-slide"));
    this.totalSlides = this.slides.length;
  }

  /* ═══════════════════════════════════════════════════════════════════════
   *  PHASE 12 — Dual-Resolution Source Selection
   *
   *  Runs BEFORE cloning. Sets src + preload="none" on every original.
   *  Clones inherit src via cloneNode(true).
   *  preload="none" = zero bytes fetched until play() is called.
   * ═══════════════════════════════════════════════════════════════════════ */

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

      if (hd && !sd) targetSrc = hd;
      else if (sd && !hd) targetSrc = sd;
      else if (hd && sd) targetSrc = preferSD ? sd : hd;

      video.dataset.selectedSrc = targetSrc;

      // Set src + preload="none" + poster — all before cloning
      if (this.posterEngine) {
        this.posterEngine.initSource(video);
      } else if (targetSrc) {
        video.setAttribute("src", targetSrc);
        video.preload = "none";
      }
    });
  }

  /* ═══════════════════════════════════════════════════════════════════════
   *  NAVIGATION
   * ═══════════════════════════════════════════════════════════════════════ */

  goTo(index) {
    this._pauseCenter();
    this._syncClonePosters();
    this.current = index;
    this._updateSlideClasses();
    this._resetDistantVideos();
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

  /* ═══════════════════════════════════════════════════════════════════════
   *  TRANSFORM — pixel-based, center-offset positioning
   * ═══════════════════════════════════════════════════════════════════════ */

  _applyTransform(animate) {
    if (!this._slideWidth) return;

    const visible = this._getVisibleCount();
    const center = this._getCenterOffset(visible);
    const tx = -(this.current - center) * this._slideWidth;

    this.track.style.transition = animate ? "" : "none";
    this.track.style.transform = `translateX(${tx}px)`;

    if (!animate) {
      void this.track.offsetHeight;
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
  }

  /* ═══════════════════════════════════════════════════════════════════════
   *  INFINITE SNAPBACK
   * ═══════════════════════════════════════════════════════════════════════ */

  _scheduleSnapback() {
    clearTimeout(this._snapTimer);

    this._snapTimer = setTimeout(() => {
      const lo = this.cloneCount;
      const hi = this.cloneCount + this.totalOrig;

      if (this.current >= lo && this.current < hi) return;

      const prevCurrent = this.current;

      if (this.current >= hi) {
        this.current -= this.totalOrig;
      } else {
        this.current += this.totalOrig;
      }

      // Pause the clone's video
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
      this._resetDistantVideos();
      this._playCenter();
    }, 550);
  }

  /* ═══════════════════════════════════════════════════════════════════════
   *  SLIDE CLASSES
   * ═══════════════════════════════════════════════════════════════════════ */

  _updateSlideClasses() {
    this.slides.forEach((slide, i) => {
      slide.classList.toggle("wphz-ugc-slide--active", i === this.current);
    });
  }

  /* ═══════════════════════════════════════════════════════════════════════
   *  INDEX-DISTANCE VIDEO RESET
   *
   *  Replaces per-slide IntersectionObserver. Calculates circular distance
   *  between each item's data-index and the active item. Items beyond the
   *  visible threshold get currentTime=0. Nearby items keep paused frame.
   *
   *  Works through snapback because circular distance is position-independent.
   * ═══════════════════════════════════════════════════════════════════════ */

  _resetDistantVideos() {
    const currentItemIndex = this._getItemIndexFromSlide(
      this.slides[this.current],
    );
    if (currentItemIndex < 0) return;

    const threshold = Math.ceil(this._getVisibleCount() / 2);

    this.slides.forEach((slide, idx) => {
      if (idx === this.current) return;

      const itemIndex = this._getItemIndexFromSlide(slide);
      if (itemIndex < 0) return;

      const diff = Math.abs(currentItemIndex - itemIndex);
      const distance = Math.min(diff, this.totalOrig - diff);

      if (distance > threshold) {
        const video = slide.querySelector(".wphz-ugc-video");
        if (video && video.currentTime !== 0) {
          video.currentTime = 0;
        }
      }
    });
  }

  /* ═══════════════════════════════════════════════════════════════════════
   *  CLONE POSTER SYNC
   *
   *  Clones are DOM copies from init — they don't inherit runtime playback
   *  state. Before every navigation, capture each played original's current
   *  frame via canvas and write it as poster on all matching clones.
   *  Synchronous (toDataURL) to avoid timing gaps.
   * ═══════════════════════════════════════════════════════════════════════ */

  _syncClonePosters() {
    const lo = this.cloneCount;
    const hi = this.cloneCount + this.totalOrig;

    for (let i = lo; i < hi; i++) {
      const video = this.slides[i]?.querySelector(".wphz-ugc-video");
      if (!video || video.readyState < 2 || !video.videoWidth) continue;

      const itemIndex = this._getItemIndexFromSlide(this.slides[i]);
      if (itemIndex < 0) continue;

      let frameUrl;
      try {
        const c = document.createElement("canvas");
        c.width = video.videoWidth;
        c.height = video.videoHeight;
        c.getContext("2d").drawImage(video, 0, 0, c.width, c.height);
        frameUrl = c.toDataURL("image/png");
      } catch (_) {
        continue;
      }

      this.slides.forEach((s, idx) => {
        if (idx >= lo && idx < hi) return;
        if (this._getItemIndexFromSlide(s) !== itemIndex) return;
        const v = s.querySelector(".wphz-ugc-video");
        if (v) v.poster = frameUrl;
      });
    }
  }

  /* ═══════════════════════════════════════════════════════════════════════
   *  VIDEO CONTROL
   *
   *  Since src is always set (preload="none"), play() just calls
   *  video.play() — browser fetches on demand. No attach/detach cycle,
   *  no poster rewrite, no black flash.
   *
   *  Pause just pauses — native paused frame stays visible.
   * ═══════════════════════════════════════════════════════════════════════ */

  _playCenter() {
    const slide = this.slides[this.current];
    const video = slide?.querySelector(".wphz-ugc-video");
    if (!video) return;

    // Clone zone guard — don't play clones, poster is sufficient
    const lo = this.cloneCount;
    const hi = this.cloneCount + this.totalOrig;
    if (this.current < lo || this.current >= hi) {
      this._applyMutedToAllSlides();
      if (this.posterEngine) {
        this.posterEngine.resetToPoster(video);
      }
      return;
    }

    this._applyMutedToAllSlides();

    const itemMuted = this._isMuted();

    const playPromise = this.posterEngine
      ? this.posterEngine.play(video, itemMuted)
      : (() => {
          video.muted = itemMuted;
          return video.play();
        })();

    if (playPromise && typeof playPromise.catch === "function") {
      playPromise.catch(() => {
        this._setGlobalMuted(true);
        video.muted = true;
        const retry = this.posterEngine
          ? this.posterEngine.play(video, true)
          : video.play();
        retry?.catch(() => {});
      });
    }

    video.onended = () => this._onVideoEnded();
  }

  _pauseCenter() {
    const slide = this.slides[this.current];
    const video = slide?.querySelector(".wphz-ugc-video");
    if (!video) return;

    // Just pause — native paused frame stays visible.
    // No src detach, no currentTime reset, no poster swap.
    if (this.posterEngine) {
      this.posterEngine.pause(video);
    } else {
      video.pause();
    }
  }

  _pauseForViewport() {
    const video = this.slides[this.current]?.querySelector(".wphz-ugc-video");
    if (!video) return;
    video.pause();
  }

  _resumeForViewport() {
    const video = this.slides[this.current]?.querySelector(".wphz-ugc-video");
    if (!video) return;
    video.currentTime = 0;
    this._playCenter();
  }

  _onVideoEnded() {
    const endedVideo =
      this.slides[this.current]?.querySelector(".wphz-ugc-video");
    if (endedVideo) {
      endedVideo.currentTime = 0;
    }
    this.direction === "rtl" ? this.prev() : this.next();
  }

  /* ═══════════════════════════════════════════════════════════════════════
   *  DRAG & SWIPE
   * ═══════════════════════════════════════════════════════════════════════ */

  _bindDrag() {
    const track = this.track;

    track.addEventListener("mousedown", (e) =>
      this._onDragStart(e.clientX, e.clientY),
    );
    window.addEventListener("mousemove", (e) =>
      this._onDragMove(e.clientX, e.clientY),
    );
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

    if (absX <= absY) return;

    if (this._drag.diffX < -threshold) this.next();
    else if (this._drag.diffX > threshold) this.prev();
  }

  /* ═══════════════════════════════════════════════════════════════════════
   *  MUTE TOGGLES
   * ═══════════════════════════════════════════════════════════════════════ */

  _bindMuteButtons() {
    this.root.addEventListener("click", (e) => {
      // Guard: ignore if this was actually a drag
      if (Math.abs(this._drag.diffX) > 12) return;

      // Don't intercept ATC buttons or product links
      if (e.target.closest(".wphz-ugc-atc-btn") || e.target.closest(".wphz-ugc-product-img-link") || e.target.closest(".wphz-ugc-product-name-link")) return;

      const muteBtn = e.target.closest(".wphz-ugc-mute-btn");
      const slide = e.target.closest(".wphz-ugc-slide");
      if (!slide) return;

      const clickedIndex = this.slides.indexOf(slide);
      if (clickedIndex === -1) return;

      if (muteBtn) {
        // Sound icon: navigate (if not current) + always toggle mute
        const nextMuted = !this._isMuted();
        this._setGlobalMuted(nextMuted);
        if (clickedIndex !== this.current) {
          this.goTo(clickedIndex);
        } else {
          const video = slide.querySelector(".wphz-ugc-video");
          if (video) video.muted = nextMuted;
        }
      } else {
        // Anywhere else on the slide: navigate only (if not current)
        if (clickedIndex !== this.current) {
          this.goTo(clickedIndex);
        }
      }
    });
  }

  _setGlobalMuted(isMuted) {
    this.isMuted = !!isMuted;
    this._applyMutedToAllSlides();
  }

  _applyMutedToAllSlides() {
    this.slides.forEach((slide) => {
      this._setSlideMuted(slide, this.isMuted);
    });
  }

  _setSlideMuted(slide, isMuted) {
    if (!slide) return;

    const video = slide.querySelector(".wphz-ugc-video");
    if (video) video.muted = !!isMuted;

    const btn = slide.querySelector(".wphz-ugc-mute-btn");
    if (!btn) return;

    const muteIcon = btn.querySelector(".wphz-icon-mute");
    const unmuteIcon = btn.querySelector(".wphz-icon-unmute");
    if (muteIcon) muteIcon.style.display = isMuted ? "" : "none";
    if (unmuteIcon) unmuteIcon.style.display = isMuted ? "none" : "";
  }

  _isMuted() {
    return !!this.isMuted;
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

    if (this.totalOrig === 1) {
      this.wrap
        .querySelector(".wphz-product-arrow--next")
        ?.style.setProperty("display", "none");
      this.wrap
        .querySelector(".wphz-product-arrow--prev")
        ?.style.setProperty("display", "none");
      return;
    }

    this.track.style.gap = "0";

    this._buildInfiniteTrack();
    this._bindArrows();

    this._resizeObserver = new ResizeObserver(() => {
      this._setItemSizes();
      this._applyTransform(false);
    });

    this._resizeObserver.observe(this.wrap);
  }

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
    this.current = this.cloneCount;
  }

  _setItemSizes() {
    this._itemWidth = this.wrap.offsetWidth;
    if (!this._itemWidth) return;

    this.items.forEach((item) => {
      item.style.flex = `0 0 ${this._itemWidth}px`;
    });
  }

  _applyTransform(animate) {
    if (!this._itemWidth) return;

    const translateX = -(this.current * this._itemWidth);

    this.track.style.transition = animate ? "" : "none";
    this.track.style.transform = `translateX(${translateX}px)`;

    if (!animate) {
      void this.track.offsetHeight;
      this.track.style.transition = "";
    }
  }

  _slide(dir) {
    this.current += dir;
    this._applyTransform(true);
    this._scheduleSnapback();
  }

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
    }, 350);
  }

  _bindArrows() {
    this.wrap
      .querySelector(".wphz-product-arrow--next")
      ?.addEventListener("click", (e) => {
        e.currentTarget?.removeAttribute("disabled");
        this._slide(1);
      });
    this.wrap
      .querySelector(".wphz-product-arrow--prev")
      ?.addEventListener("click", (e) => {
        e.currentTarget?.removeAttribute("disabled");
        this._slide(-1);
      });
  }
}

/* ── Boot Process & Global API ────────────────────────────────────────────── */
window.wphzUGCFrontend = window.wphzUGCFrontend || {};
window.wphzUGCFrontend.instances = {};

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".wphz-ugc-carousel").forEach((el) => {
    const id = el.dataset.carouselId;
    const instance = new WPHZUGCCarousel(el);
    if (id) {
      window.wphzUGCFrontend.instances[id] = instance;
    }
  });

  document
    .querySelectorAll(".wphz-ugc-products--carousel")
    .forEach((el) => new WPHZProductCarousel(el));
});

/* ── External Button Binding ─────────────────────────────────────────────── */
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