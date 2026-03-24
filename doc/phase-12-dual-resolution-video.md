# Phase 12: Dual-Resolution Video Support

To replicate Tolstoy's conditional video loading, this phase will split the current single-video architecture into an exact High-Resolution vs Low-Resolution infrastructure.

## Executive Requirements: Dual-Source Parity & Failover
**Requirement 1 (UI Parity):** Every video row must have full capabilities for both resolutions. This means there must be a Media Library button AND a Custom URL input field explicitly for HD, and a completely separate set explicitly for SD. 
**Requirement 2 (Graceful Failover):** If the user only provides an HD video (leaving SD empty), the slider skips connection checks and unconditionally plays the HD video for all users. Conversely, if only an SD video is provided (leaving HD empty), it unconditionally plays the SD video.

## 1. Database Schema & Migration (`src/Installer/Installer.php`)

_Goal: Evolve the data layer to support two distinct URLs per slide._

- [ ] Rename `video_url` column to `video_url_hd` in `wp_wphz_ugc_items` table.
- [ ] Add new column `video_url_sd` (`TEXT`).
- [ ] Write a safe migration script in `Installer.php` to port any existing `video_url` data into `video_url_hd`.
- [ ] Bump `WPHZ_UGC_VERSION` to explicitly trigger the `admin_init` migration we built in Phase 10.

## 2. Server-side Repository Routing (`src/Repository/ItemRepository.php`)

_Goal: Ensure the PHP engine handles both string payloads simultaneously._

- [ ] Refactor the `get_all` formatting loop to fetch the two new columns instead of `video_url`.
- [ ] Refactor the insertion/update logic to execute against `video_url_hd` and `video_url_sd`.

## 3. Admin Editor UI Refactoring (`assets/admin/admin.js` & `templates/`)

_Goal: Provide the user intuitive inputs for both video strings within the draggable sortable row._

- [ ] Update `wphzBuildItemRow` inside `admin.js` to render a grouped UI array for HD (Media Button + Custom URL input) and a separate grouped UI array for SD (Media Button + Custom URL input).
- [ ] Ensure click handlers correctly route the WordPress Media Uploader returns to the corresponding HD or SD input field depending on which button was clicked.
- [ ] Refactor the JS payload mapper (`$form.serializeArray` mapping) in `admin.js` to send both nodes during the Ajax Save constraint.

## 4. AJAX Handler Transformation (`src/Ajax/SaveContent.php`)

_Goal: Intercept and validate both new array strings from the `$_POST` form._

- [ ] Refactor `$data['video_url']` parsing to expect and sanitize `$data['video_url_hd']` and `$data['video_url_sd']`.
- [ ] If an SD URL is not provided, gracefully fall back to executing the HD URL in both slots in the database.

## 5. Frontend & JS Governor (`templates/frontend/carousel-item.php` & `carousel.js`)

_Goal: Delete the hardcoded `<video src>` logic and evaluate viewport metrics dynamically._

- [ ] In `carousel-item.php`, replace `<video src="...">` with `<video data-src-hd="..." data-src-sd="...">` ensuring empty strings are passed if a field is missing.
- [ ] In `carousel.js`, during boot or play sequences, implement strict fallback routing:
  - If `data-src-hd` exists but `SD` is empty → Play HD unconditionally.
  - If `data-src-sd` exists but `HD` is empty → Play SD unconditionally.
- [ ] If BOTH exist, assess connection (`navigator.connection.downlink`) / viewport bounds to dynamically inject the correct `.src` string into the element.
