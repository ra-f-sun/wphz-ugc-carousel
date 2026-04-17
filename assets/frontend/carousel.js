/**
 * WPHZ UGC Carousel — Frontend JavaScript Engine
 * 
 */

class WPHZUGCCarousel {
  /**
   * @param {HTMLElement} el - The carousel root element (.wphz-ugc-carousel).
   */
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
    this._isShifting = false;  // re-entrancy guard for goTo()
    this._shiftQueue = null;   // stores { index } of last blocked navigation request
    this._playEpoch  = 0;      // incremented each goTo(); guards stale play() callbacks

    this.posterEngine =
      typeof window.WPHZUGCPosterEngine === "function"
        ? new window.WPHZUGCPosterEngine(el)
        : null;

    this.init();
  }

  /**
   * Set up video sources, infinite track, drag, mute, ResizeObserver,
   * and IntersectionObserver. Called once from the constructor.
   *
   * @return {void}
   */
  init() {
    if (this.totalOrig === 0) return;

    this._setVideoSources();    // Select resolution + set src + preload="none"
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

  /**
   * Recalculate slide width from stage width and apply flex sizing to all slides.
   *
   * @return {void}
   */
  _setSlideSizes() {
    const stageWidth = this.stage.offsetWidth;
    if (!stageWidth) return;

    const visible = this._getVisibleCount();
    this._slideWidth = stageWidth / visible;

    this.slides.forEach((slide) => {
      slide.style.flex = `0 0 ${this._slideWidth}px`;
    });
  }

  /**
   * Prepend and append full copies of all original slides to enable seamless
   * infinite looping. Populates this.slides and this.totalSlides.
   *
   * @return {void}
   */
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

  /**
   * Select HD or SD video source per slide based on connection speed and
   * viewport width, then set src + preload="none" before cloning.
   *
   * @return {void}
   */
  _setVideoSources() {
    const isSlow = navigator.connection && navigator.connection.downlink < 3;
    const isMobile = window.innerWidth <= 768;
    const preferSD = isSlow || isMobile;

    this.origSlides.forEach((slide) => {
      const video = slide.querySelector(".wphz-ugc-video");
      if (!video) return;

      const hdVideoUrl = video.dataset.srcHd;
      const sdVideoUrl = video.dataset.srcSd;
      let targetSrc = "";

      if (hdVideoUrl && !sdVideoUrl) targetSrc = hdVideoUrl;
      else if (sdVideoUrl && !hdVideoUrl) targetSrc = sdVideoUrl;
      else if (hdVideoUrl && sdVideoUrl) targetSrc = preferSD ? sdVideoUrl : hdVideoUrl;

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

  /**
   * Navigate to the given slide index with re-entrancy protection.
   *
   * Concurrent calls while a shift is in progress are queued; only the
   * most recent queued request is replayed after the current shift completes.
   * Uses an epoch counter to cancel stale async play() callbacks.
   *
   * @param {number} index - Target slide index (0-based across the full cloned track).
   * @return {void}
   */
  goTo(index) {
    if (this._isShifting) {
      // Queue only the most recent request; earlier queued requests are discarded.
      this._shiftQueue = { index };
      return;
    }

    this._isShifting = true;
    this._shiftQueue = null;
    this._playEpoch++;

    this._prepareShift(index);
    this._completeShift();

    // Release lock after CSS transition completes (500ms).
    // _scheduleSnapback fires at 550ms; it calls clearTimeout(_snapTimer) on
    // entry, so a queued goTo firing here correctly replaces the pending snapback.
    setTimeout(() => {
      this._isShifting = false;
      if (this._shiftQueue !== null) {
        const queued = this._shiftQueue;
        this._shiftQueue = null;
        this.goTo(queued.index);
      }
    }, 500);
  }

  /**
   * Pause current video, sync clone posters, update index and slide classes,
   * reset distant videos. Called at the start of every shift.
   *
   * @param {number} index - The target slide index.
   * @return {void}
   */
  _prepareShift(index) {
    this._pauseCenter();
    this._syncClonePosters();
    this.current = index;
    this._updateSlideClasses();
    this._resetDistantVideos();
  }

  /**
   * Apply the CSS transform, start playback on the new center slide,
   * and schedule the infinite-loop snapback. Called at the end of every shift.
   *
   * @return {void}
   */
  _completeShift() {
    this._applyTransform(true);
    this._playCenter();
    this._scheduleSnapback();
  }

  /**
   * Navigate one slide forward (respecting direction).
   *
   * @return {void}
   */
  next() {
    this.goTo(this.current + 1);
  }

  /**
   * Navigate one slide backward (respecting direction).
   *
   * @return {void}
   */
  prev() {
    this.goTo(this.current - 1);
  }

  /**
   * Translate the track to center the current slide.
   *
   * @param {boolean} animate - Whether to apply the CSS transition.
   * @return {void}
   */
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

  /**
   * @return {number} Number of visible slides for the current viewport width.
   */
  _getVisibleCount() {
    if (window.innerWidth <= 768) return 1.7;
    if (window.innerWidth <= 1024) return 3.3;
    return 4.0;
  }

  /**
   * @param {number} visible - Output of _getVisibleCount().
   * @return {number} Fractional slide offset that centers the active slide.
   */
  _getCenterOffset(visible) {
    if (window.innerWidth <= 768) return (visible - 1) / 2;
    return Math.max(0, visible - 2.25);
  }

  /**
   * Schedule a silent position correction when the track has scrolled into a
   * clone zone. Fires 550ms after navigation (after the 500ms CSS transition).
   *
   * @return {void}
   */
  _scheduleSnapback() {
    clearTimeout(this._snapTimer);

    this._snapTimer = setTimeout(() => {
      const cloneRangeStart = this.cloneCount;
      const cloneRangeEnd   = this.cloneCount + this.totalOrig;

      if (this.current >= cloneRangeStart && this.current < cloneRangeEnd) return;

      const prevCurrent = this.current;

      if (this.current >= cloneRangeEnd) {
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

  /**
   * Toggle the --active modifier class to match this.current.
   *
   * @return {void}
   */
  _updateSlideClasses() {
    this.slides.forEach((slide, i) => {
      slide.classList.toggle("wphz-ugc-slide--active", i === this.current);
    });
  }

  /**
   * Reset videos that are too far from the active slide back to their poster
   * frame. Uses circular item-index distance so clone positions are irrelevant.
   *
   * @return {void}
   */
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
          if (this.posterEngine) this.posterEngine.resetToPoster(video);
          video.currentTime = 0;
        }
      }
    });
  }

  /**
   * Capture the current frame of each playing original slide and write it as
   * the poster on all corresponding clone slides.
   *
   * @return {void}
   */
  _syncClonePosters() {
    const cloneRangeStart = this.cloneCount;
    const cloneRangeEnd   = this.cloneCount + this.totalOrig;

    for (let i = cloneRangeStart; i < cloneRangeEnd; i++) {
      const video = this.slides[i]?.querySelector(".wphz-ugc-video");
      if (!video || video.readyState < 2 || !video.videoWidth) continue;

      const itemIndex = this._getItemIndexFromSlide(this.slides[i]);
      if (itemIndex < 0) continue;

      let frameUrl;
      try {
        const canvas = document.createElement("canvas");
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext("2d").drawImage(video, 0, 0, canvas.width, canvas.height);
        frameUrl = canvas.toDataURL("image/png");
      } catch (_) {
        continue;
      }

      this.slides.forEach((slide, idx) => {
        if (idx >= cloneRangeStart && idx < cloneRangeEnd) return;
        if (this._getItemIndexFromSlide(slide) !== itemIndex) return;
        const videoElement = slide.querySelector(".wphz-ugc-video");
        if (videoElement) videoElement.poster = frameUrl;
      });
    }
  }

  /**
   * Play the video at the center slide. Skips clone-zone slides, applies mute
   * state, and retries with mute forced on if autoplay policy blocks playback.
   *
   * @return {void}
   */
  _playCenter() {
    const slide = this.slides[this.current];
    const video = slide?.querySelector(".wphz-ugc-video");
    if (!video) return;

    // Clone zone guard — don't play clones, poster is sufficient
    const cloneRangeStart = this.cloneCount;
    const cloneRangeEnd   = this.cloneCount + this.totalOrig;
    if (this.current < cloneRangeStart || this.current >= cloneRangeEnd) {
      this._applyMutedToAllSlides();
      if (this.posterEngine) {
        this.posterEngine.resetToPoster(video);
      }
      return;
    }

    this._applyMutedToAllSlides();

    const itemMuted = this._isMuted();
    const epoch = this._playEpoch; // capture before async boundary

    const playPromise = this.posterEngine
      ? this.posterEngine.play(video, itemMuted)
      : (() => {
          video.muted = itemMuted;
          return video.play();
        })();

    if (playPromise && typeof playPromise.catch === "function") {
      playPromise.catch(() => {
        if (this._playEpoch !== epoch) return; // stale — a newer navigation fired
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

  /**
   * Pause the video at the current center slide. Preserves the native paused frame.
   *
   * @return {void}
   */
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

  /**
   * Pause center video when the carousel scrolls out of the viewport.
   *
   * @return {void}
   */
  _pauseForViewport() {
    const video = this.slides[this.current]?.querySelector(".wphz-ugc-video");
    if (!video) return;
    video.pause();
  }

  /**
   * Reset and play the center video when the carousel scrolls back into view.
   *
   * @return {void}
   */
  _resumeForViewport() {
    const video = this.slides[this.current]?.querySelector(".wphz-ugc-video");
    if (!video) return;
    video.currentTime = 0;
    this._playCenter();
  }

  /**
   * Handle natural video end — reset currentTime and auto-advance.
   *
   * @return {void}
   */
  _onVideoEnded() {
    const endedVideo =
      this.slides[this.current]?.querySelector(".wphz-ugc-video");
    if (endedVideo) {
      endedVideo.currentTime = 0;
    }
    // Don't auto-advance if a manual navigation is already in progress.
    if (this._isShifting) return;
    this.direction === "rtl" ? this.prev() : this.next();
  }

  /**
   * Attach mouse and touch drag listeners to the track.
   *
   * @return {void}
   */
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

  /**
   * @param {number} x - Pointer X coordinate.
   * @param {number} y - Pointer Y coordinate.
   * @return {void}
   */
  _onDragStart(x, y) {
    this._drag.active = true;
    this._drag.startX = x;
    this._drag.startY = y;
    this._drag.diffX = 0;
    this._drag.diffY = 0;
  }

  /**
   * @param {number} x - Current pointer X coordinate.
   * @param {number} y - Current pointer Y coordinate.
   * @return {void}
   */
  _onDragMove(x, y) {
    if (!this._drag.active || !this._slideWidth) return;
    this._drag.diffX = x - this._drag.startX;
    this._drag.diffY = y - this._drag.startY;
  }

  /**
   * Determine swipe direction from accumulated drag delta and navigate.
   *
   * @return {void}
   */
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

  /**
   * Attach a delegated click listener on the carousel root for mute-button
   * and slide-navigation interactions.
   *
   * @return {void}
   */
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

  /**
   * @param {boolean} isMuted - New global mute state.
   * @return {void}
   */
  _setGlobalMuted(isMuted) {
    this.isMuted = !!isMuted;
    this._applyMutedToAllSlides();
  }

  /**
   * Apply the current global mute state to every slide.
   *
   * @return {void}
   */
  _applyMutedToAllSlides() {
    this.slides.forEach((slide) => {
      this._setSlideMuted(slide, this.isMuted);
    });
  }

  /**
   * Apply mute state to a single slide's video and update the mute-button icons.
   *
   * @param {HTMLElement} slide   - The slide element.
   * @param {boolean}     isMuted - Whether to mute.
   * @return {void}
   */
  _setSlideMuted(slide, isMuted) {
    if (!slide) return;

    const video = slide.querySelector(".wphz-ugc-video");
    if (video) video.muted = !!isMuted;

    const btn = slide.querySelector(".wphz-ugc-mute-btn");
    if (!btn) return;

    const muteIcon   = btn.querySelector(".wphz-icon-mute");
    const unmuteIcon = btn.querySelector(".wphz-icon-unmute");
    if (muteIcon)   muteIcon.style.display   = isMuted ? "" : "none";
    if (unmuteIcon) unmuteIcon.style.display = isMuted ? "none" : "";
  }

  /**
   * @return {boolean} Current global mute state.
   */
  _isMuted() {
    return !!this.isMuted;
  }

  /**
   * Extract the data-index attribute from a slide as a number.
   *
   * @param {HTMLElement|null} slide - A slide element.
   * @return {number} The item index, or -1 if missing/invalid.
   */
  _getItemIndexFromSlide(slide) {
    if (!slide) return -1;
    const raw = slide.dataset.index;
    const parsed = Number.parseInt(raw || "", 10);
    return Number.isNaN(parsed) ? -1 : parsed;
  }
}


class WPHZProductCarousel {
  /**
   * @param {HTMLElement} el - The product carousel wrapper element.
   */
  constructor(el) {
    this.wrap = el;
    this.track = el.querySelector(".wphz-ugc-products-track");
    this.origItems = Array.from(el.querySelectorAll(".wphz-ugc-product-item"));
    this.totalOrig = this.origItems.length;

    this._itemWidth = 0;
    this._snapTimer = null;
    this._resizeObserver = null;
    this._isSliding = false; // re-entrancy guard for _slide()

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

  /**
   * Prepend and append full copies of all original items for infinite looping.
   *
   * @return {void}
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
    this.current = this.cloneCount;
  }

  /**
   * Measure wrapper width and apply it as flex-basis to all items.
   *
   * @return {void}
   */
  _setItemSizes() {
    this._itemWidth = this.wrap.offsetWidth;
    if (!this._itemWidth) return;

    this.items.forEach((item) => {
      item.style.flex = `0 0 ${this._itemWidth}px`;
    });
  }

  /**
   * Translate the track to show the current item.
   *
   * @param {boolean} animate - Whether to apply the CSS transition.
   * @return {void}
   */
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

  /**
   * Slide one step in the given direction with re-entrancy guard.
   *
   * @param {number} dir - 1 for next, -1 for previous.
   * @return {void}
   */
  _slide(dir) {
    if (this._isSliding) return;
    this._isSliding = true;
    this.current += dir;
    this._applyTransform(true);
    this._scheduleSnapback();
    setTimeout(() => { this._isSliding = false; }, 350);
  }

  /**
   * Schedule a silent position correction when the track enters a clone zone.
   *
   * @return {void}
   */
  _scheduleSnapback() {
    clearTimeout(this._snapTimer);

    this._snapTimer = setTimeout(() => {
      const cloneRangeStart = this.cloneCount;
      const cloneRangeEnd   = this.cloneCount + this.totalOrig;

      if (this.current >= cloneRangeStart && this.current < cloneRangeEnd) return;

      if (this.current >= cloneRangeEnd) {
        this.current -= this.totalOrig;
      } else {
        this.current += this.totalOrig;
      }

      this._applyTransform(false);
    }, 350);
  }

  /**
   * Attach click listeners to the next/prev arrow buttons.
   *
   * @return {void}
   */
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

/* Boot Process & Global API */
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

/* External Button Binding */
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

/* Add to Cart Integration */
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
    action:     "wphz_ugc_add_to_cart",
    product_id: productId,
    quantity:   1,
    nonce:      nonce,
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
