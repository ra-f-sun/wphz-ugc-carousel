# Phase 10: Multi-Carousel Architecture Implementation Checklist

> **Context for Output Agent:** The client wants the ability to create multiple distinct carousels, each with its own configuration (Heading, Mute, JS Events) and its own set of videos/products. Each carousel must generate a unique shortcode like `[wphz_ugc_carousel id="1"]`.
> 
> Currently, the plugin utilizes a single global configuration stored in `wp_options` and uses `carousel_id = 'default'` in the `wp_wphz_ugc_items` table. Your goal is to migrate to a true parent/child architecture using a new `wp_wphz_ugc_carousels` table.
>
> **Important:** Perform these steps exactly as listed to avoid breaking dependencies. When completed, delete the global options `wphz_ugc_config` and `wphz_ugc_danger_config`.

---

## 1. Database Schema & Migration (`src/Installer/Installer.php`)
- [ ] In `Installer::create_tables()`, add the SQL to create `wp_wphz_ugc_carousels`:
    ```sql
    CREATE TABLE {$wpdb->prefix}wphz_ugc_carousels (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        heading VARCHAR(255),
        subheading VARCHAR(255),
        mute TINYINT(1) DEFAULT 1,
        direction VARCHAR(10) DEFAULT 'ltr',
        on_arrow_right VARCHAR(255),
        on_arrow_left VARCHAR(255),
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset};
    ```
- [ ] Add an upgrade migration method `migrate_to_multi_carousel()` that runs during `activate()`:
    1. Check if the new table is empty.
    2. If empty, check if `get_option('wphz_ugc_config')` exists.
    3. If it exists, read the old global config and danger config.
    4. Insert a single record into `wp_wphz_ugc_carousels` with `id = 1`, `name = 'Default Carousel'`, and the old config values.
    5. Update all rows in `wp_wphz_ugc_items` where `carousel_id = 'default'` to `carousel_id = '1'`.
    6. Delete the old `wp_options` (`wphz_ugc_config` and `wphz_ugc_danger_config`).

## 2. Refactor Data Layer (`src/Repository/`)
- [ ] Completely rewrite `CarouselRepository.php` so it no longer reads/writes to `wp_options`. It instead performs CRUD operations on the new `wp_wphz_ugc_carousels` table:
    - `get_all(): array`
    - `get_by_id(int $id): ?array`
    - `insert(array $data): int|false`
    - `update(int $id, array $data): bool`
    - `delete(int $id): bool`
- [ ] In `ItemRepository.php`, update `get_all(string $carousel_id)` to ensure it takes the `$carousel_id` when fetching the items for that specific carousel instance. Update `insert` to ensure it saves the correct `carousel_id` instead of hardcoding `'default'`.

## 3. Refactor Admin UIs (`src/Admin/` and `templates/admin/`)
- [ ] Change `AdminMenu.php` to handle three views instead of two tabs:
    1. **List View** (`?page=wphz-ugc-carousel`): A table showing all created carousels, their ID, Name, and Shortcode (`[wphz_ugc_carousel id="X"]`), plus an "Edit" and "Delete" button. Also an "Add New Carousel" button at the top.
    2. **Add/Edit View** (`?page=wphz-ugc-carousel&action=edit&id=X`): The actual config page UI (Config and Content tabs scoped to `$_GET['id']`).
- [ ] Refactor `ConfigPage.php` and `ContentPage.php` so their `get_data()` methods accept an `$id`. They must fetch data for that specific ID from `CarouselRepository` and `ItemRepository`.
- [ ] Update `layout.php` to conditionally render either the List View or the Edit Tabs view based on the URL parameters (`$_GET['action']`).
- [ ] Update `config-tab.php` and `content-tab.php` to include a hidden input field: `<input type="hidden" name="carousel_id" value="<?php echo esc_attr($id); ?>">`.
- [ ] Create a new layout template `templates/admin/list-view.php` for displaying the table of all carousels.

## 4. Refactor Admin AJAX Handlers (`src/Ajax/`)
- [ ] `SaveConfig.php`: Update `handle()` to expect `carousel_id` in `$_POST`. Call `CarouselRepository::instance()->update((int)$carousel_id, $data)`.
- [ ] `SaveContent.php`: Update `handle()` to expect `carousel_id` in `$_POST`. When inserting new items or updating existing ones, ensure the specific `$carousel_id` is assigned.
- [ ] Create a new `CreateCarousel.php` AJAX handler (or handle it directly via POST back to the menu page) that takes a `name` and calls `CarouselRepository::instance()->insert()`, then redirects to the edit tab for that new ID.

## 5. Refactor Shortcode Engine (`src/Shortcode/`)
- [ ] Update `ShortcodeRegistrar.php` or `ShortcodeRenderer.php` to extract the `id` attribute: `shortcode_atts(['id' => 0], $atts)`.
- [ ] If `id === 0`, return a polite error string: `"WPHZ UGC Carousel: Please provide a valid carousel ID in the shortcode."`.
- [ ] Use `CarouselRepository::instance()->get_by_id($id)` to fetch the config.
- [ ] Use `ItemRepository::instance()->get_all((string) $id)` to fetch the items.
- [ ] Pass the localized data into `TemplateLoader::render('frontend/carousel-wrapper', [...])`.

## 6. Frontend Verification (`assets/frontend/`)
- [ ] **No changes to `carousel.js` should be required**, as the initialization already loops over all `.wphz-ugc-carousel` elements independently and creates isolated JS class instances! Just do a final manual check to ensure multiple shortcodes on the same page don't bleed variables.
- [ ] Verify that `carousel.css` classes don't conflict, which shouldn't happen natively with BEM architecture.
- [ ] Sync all files via SFTP and test adding two distinct carousels with two distinct shortcodes on a WordPress test page.
