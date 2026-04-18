=== UGC Carousels & Shoppable Videos for WooCommerce ===
Contributors: wphelpzone
Tags: ugc, carousel, woocommerce, video, shoppable video
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Requires Plugins: woocommerce
Stable tag: 1.0.8
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display shoppable UGC video carousels on your WooCommerce store. Attach products to each video slide for inline add-to-cart.

== Description ==

**UGC Carousels & Shoppable Videos for WooCommerce** lets you embed vertical video carousels on any page using a simple shortcode. Each video slide can have WooCommerce products attached so shoppers can add items to cart without leaving the carousel.

**Key Features:**

* Infinite-looping vertical video carousel with smooth CSS transitions
* Dual-resolution video sources — HD for broadband, SD for mobile/slow connections
* Attach one or multiple WooCommerce products to each video slide
* Inline AJAX add-to-cart with WooCommerce fragment refresh for mini-cart updates
* Product sub-carousel for multi-product slides with arrow navigation
* Drag and swipe support (mouse and touch)
* Mute / unmute toggle per carousel with autoplay-blocked fallback
* Left-to-right or right-to-left slide direction
* Per-carousel custom CSS editor with CodeMirror syntax highlighting
* CSV export and import for bulk content management
* Multi-carousel support — create unlimited carousels, each with its own shortcode
* External button binding — trigger next/prev from any element via HTML data attributes
* Lazy asset loading — frontend JS/CSS only enqueued on pages that use the shortcode
* Viewport-aware playback — pauses when scrolled out of view, resumes on return

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory, or install directly through the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Ensure WooCommerce is installed and active.
4. Go to **UGC Carousel** in the WordPress admin sidebar to create your first carousel.
5. Add video rows, attach products, configure settings, and copy the shortcode.
6. Paste `[wphz_ugc_carousel id="1"]` into any page or post.

== Frequently Asked Questions ==

= What video formats are supported? =

Any format supported by the HTML5 `<video>` element. MP4 (H.264) is recommended for the broadest device compatibility.

= Can I use self-hosted videos or must I use a CDN? =

Both work. You can upload videos to the WordPress Media Library or paste any external HTTPS URL.

= Does it work without WooCommerce? =

No. WooCommerce is required. The plugin displays an admin notice and will not initialise if WooCommerce is not active.

= How do I connect external Next/Previous buttons? =

Add `data-ugcc-target="CAROUSEL_ID"` and `data-ugcc-action="next"` (or `"prev"`) to any HTML element. No JavaScript is required on your end.

= Can I place multiple carousels on the same page? =

Yes. Each shortcode instance is fully independent. Use `[wphz_ugc_carousel id="1"]`, `[wphz_ugc_carousel id="2"]`, and so on.

= How does the add-to-cart button work? =

The button fires an AJAX request and updates the WooCommerce mini-cart via fragment refresh — no page reload required. Works for both logged-in and guest users.

== Screenshots ==

1. Frontend video carousel with attached products and add-to-cart buttons.
2. Admin carousel list view with shortcode column.
3. Admin content tab — video rows with product search and drag-to-reorder.
4. Admin configuration tab — sound, direction, and ATC visibility settings.
5. Admin custom CSS tab with CodeMirror editor.

== Changelog ==

= 1.0.8 =
* Security hardening across all AJAX handlers
* Dual-resolution video source support (HD + SD) with mobile auto-selection
* Per-carousel scoped custom CSS editor
* CSV export and import for bulk carousel management
* Poster image field per video slide
* 3-state per-product add-to-cart override (inherit global / force show / force hide)
* Duplicate carousel action

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.8 =
Recommended update. Includes security improvements, new features, and database migrations that run automatically on activation.
