/**
 * WPHZ UGC Poster Engine
 *
 * Strategy (inspired by Tolstoy's carousel):
 *   Every <video> gets its src set at init time with preload="none".
 *   preload="none" = zero network requests, identical to having no src.
 *   When play() is called, the browser fetches on demand — no intermediate
 *   attachSource → load() step, no black flash, no poster rewrite.
 *
 * Responsibilities:
 *   - Apply configured poster URLs via HTMLVideoElement.poster
 *   - Set src + preload="none" once (initSource) — called before cloning
 *   - Play: just call video.play() — src is already there
 *   - Pause: just call video.pause() — src stays, paused frame preserved
 *   - Reset: pause + currentTime = 0 — poster shows natively when no frame decoded
 *   - Diagnostics for cross-origin media failures
 */
(function () {
  class WPHZUGCPosterEngine {
    constructor(root) {
      this.root = root;
      this._diagnosed = new WeakSet();
    }

    /**
     * Apply the configured poster URL and crossorigin attribute.
     * Called during init and safe to call multiple times.
     */
    applyPoster(video) {
      if (!video) return;

      const posterUrl = (video.dataset.posterUrl || "").trim();
      if (posterUrl) {
        video.poster = posterUrl;
      }

      if (!video.hasAttribute("crossorigin")) {
        video.setAttribute("crossorigin", "anonymous");
      }

      this._bindDiagnostics(video);
    }

    /**
     * Set src + preload="none" on a video element.
     * Called once per original slide BEFORE cloning so clones inherit it.
     * preload="none" = browser makes zero network requests until play().
     */
    initSource(video) {
      if (!video) return;

      const selectedSrc = (video.dataset.selectedSrc || "").trim();
      if (!selectedSrc) {
        this.applyPoster(video);
        return;
      }

      video.setAttribute("src", selectedSrc);
      video.preload = "none";
      this.applyPoster(video);
    }

    /**
     * Reset video to poster state.
     * Pauses, resets time to 0. Does NOT remove src.
     * With preload="none" and currentTime=0, the browser shows the poster
     * natively if no frames are decoded yet.
     */
    resetToPoster(video) {
      if (!video) return;

      video.pause();
      try {
        video.currentTime = 0;
      } catch (_) {
        // Ignore non-seekable edge cases
      }
    }

    /**
     * Play a video. Since src is always set (from initSource), just play.
     * Skip any re-initialization if video already has decoded frames.
     */
    play(video, isMuted) {
      if (!video) return Promise.reject(new Error("Missing video element"));

      video.muted = !!isMuted;

      const playPromise = video.play();
      if (playPromise && typeof playPromise.then === "function") {
        return playPromise;
      }

      return Promise.resolve();
    }

    /**
     * Pause a video. src stays attached, paused frame preserved natively.
     */
    pause(video) {
      if (!video) return;
      video.pause();
    }

    /** Bind one-time error diagnostics per video element. */
    _bindDiagnostics(video) {
      if (!video || this._diagnosed.has(video)) return;
      this._diagnosed.add(video);

      video.addEventListener("error", () => {
        const src = video.currentSrc || video.src || "(empty-src)";
        console.warn(
          "WPHZ UGC: media failed to load. If this URL is third-party hosted, verify CORS and media headers on the origin.",
          src,
        );
      });
    }
  }

  window.WPHZUGCPosterEngine = WPHZUGCPosterEngine;
})();