# Phase 11: External Event-Based Controls & UI Stripping

This checklist outlines the steps required to remove the built-in WPHZ carousel headings, subheadings, and arrows, and replace the rigid sliding mechanics with a scalable, dynamic CustomEvent architecture. 

## 1. Admin UI & Backend Cleanup
*Goal: Prevent user confusion by removing obsolete configuration fields.*
- [ ] Edit `templates/admin/config-tab.php`: Remove inputs for **Heading**, **Subheading**, and **Arrow CSS Selectors**.
- [ ] Edit `assets/admin/admin.js`: Remove those fields from the `SaveConfig` AJAX payload submission.
- [ ] Edit `src/Ajax/SaveConfig.php`: Keep the backend lightweight by strictly saving only `mute`, `direction`, and the internal `name`.

## 2. Shortcode Template Refactoring
*Goal: Make the rendered carousel purely a structural "engine" without an opinionated HTML shell.*
- [ ] Edit `src/Shortcode/ShortcodeRenderer.php`: Stop passing `heading`, `subheading`, etc., to the frontend template variables.
- [ ] Edit `templates/frontend/carousel-wrapper.php`: 
  - Remove the `<h1>` heading and `<h2>` subheading blocks completely.
  - Remove the built-in HTML `<button>` elements for `.wphz-arrow-prev` and `.wphz-arrow-next`.
  - Ensure the outermost wrapper strictly retains `data-carousel-id="<?php echo esc_attr($id); ?>"`.

## 3. Frontend JavaScript (Event Engine)
*Goal: Re-architecture the vanilla JS class to listen to the DOM rather than hardcoded buttons.*
- [ ] Edit `assets/frontend/carousel.js`: 
  - Remove all old `click` event listeners mapped to the internal HTML arrows.
  - Create a global `carouselInstances` dictionary that maps initialized `WphzCarousel` objects to their specific carousel ID.
  - Attach two document-level listeners:
    - `document.addEventListener('wphz_carousel_slide_next', function(e) { ... })`
    - `document.addEventListener('wphz_carousel_slide_prev', function(e) { ... })`
  - In the event listeners, extract `e.detail.id`. If a matching carousel instance exists in the dictionary, execute its `next()` or `prev()` sliding function.

## 4. Developer API Exposure (Optional but Recommended)
*Goal: Provide a fallback window object for absolute granular control.*
- [ ] Expose the internal `carouselInstances` mapping to `window.wphzUGCFrontend.instances` so that power users can directly execute methods like `window.wphzUGCFrontend.instances[2].pause()` or `.next()` if they prefer not to use `CustomEvent`.

## 5. Documentation Handover
*Goal: Provide the site builder with the exact snippet required for Elementor.*
- [ ] Output a README block or display a small help tip inside the WP Admin UI illustrating the standard JS integration code block for binding custom icons to the new event bus.
