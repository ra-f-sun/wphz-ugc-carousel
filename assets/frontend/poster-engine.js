/**
 * WPHZ UGC Poster Engine
 * - Keeps non-active slides in poster-only state
 * - Applies configured poster URLs natively via HTMLVideoElement.poster
 * - Attaches video src just-in-time for active slide playback
 * - Detaches src for inactive slides to reduce decoder/memory pressure
 * - Emits lightweight diagnostics for cross-origin media failures
 */
(function () {
  class WPHZUGCPosterEngine {
    constructor(root) {
      this.root = root;
      this._diagnosed = new WeakSet();
    }

    applyPoster(video) {
      if (!video) return;

      const posterUrl = (video.dataset.posterUrl || "").trim();
      if (posterUrl) {
        video.poster = posterUrl;
      }

      // Keep cross-origin mode explicit for third-party hosts.
      // If origin doesn't send proper CORS headers, playback/poster failures
      // must be fixed on the media host side.
      if (!video.hasAttribute("crossorigin")) {
        video.setAttribute("crossorigin", "anonymous");
      }

      this._bindDiagnostics(video);
    }

    resetToPoster(video) {
      if (!video) return;

      video.pause();
      try {
        video.currentTime = 0;
      } catch (error) {
        // Ignore non-seekable edge cases; poster remains primary fallback.
      }

      if (this._hasPoster(video)) {
        this.detachSource(video);
      } else {
        // No explicit poster URL: keep metadata source attached so browser can
        // show first-frame fallback instead of a black tile.
        this.attachSource(video);
      }
      this.applyPoster(video);
    }

    attachSource(video) {
      if (!video) return;

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

    detachSource(video) {
      if (!video) return;
      if (!video.getAttribute("src")) return;

      video.removeAttribute("src");
      video.load();
    }

    play(video, isMuted) {
      if (!video) return Promise.reject(new Error("Missing video element"));

      this.attachSource(video);
      video.muted = !!isMuted;

      const playPromise = video.play();
      if (playPromise && typeof playPromise.then === "function") {
        return playPromise;
      }

      return Promise.resolve();
    }

    pause(video) {
      if (!video) return;
      video.pause();
    }

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

    _hasPoster(video) {
      return !!(video?.dataset?.posterUrl || "").trim();
    }
  }

  window.WPHZUGCPosterEngine = WPHZUGCPosterEngine;
})();
