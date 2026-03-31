<?php
namespace WPHZ\UGC\Shortcode;
defined('ABSPATH') || exit;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Repository\CarouselRepository;
use WPHZ\UGC\Repository\ItemRepository;
use WPHZ\UGC\Helpers\TemplateLoader;

class ShortcodeRenderer extends AbstractSingleton {

    /**
     * Main shortcode callback. Returns rendered HTML string (never echoes).
     *
     * @param  array<string, string>|string $atts  Shortcode attributes.
     * @return string
     */
    public function render(array|string $atts): string {
        $atts = shortcode_atts(['id' => 0], (array) $atts, 'wphz_ugc_carousel');
        $id   = (int) $atts['id'];

        if ($id <= 0) {
            return '<p>WPHZ UGC Carousel: Please provide a valid carousel ID in the shortcode.</p>';
        }

        $config = CarouselRepository::instance()->get_by_id($id);
        if (!$config) {
            return sprintf('<p>WPHZ UGC Carousel: Carousel #%d not found.</p>', $id);
        }

        $items = ItemRepository::instance()->get_all((string) $id);
        $items = $this->hydrate_items($items);

        if (empty($items)) {
            return '';
        }

        // Lazy-enqueue frontend assets — AssetLoader receives the unified config array
        \WPHZ\UGC\Frontend\AssetLoader::instance()->enqueue_now($config);

        // Prepend scoped custom CSS if the carousel has any.
        // wp_add_inline_style() cannot be used here because shortcodes render
        // during the_content(), which is after wp_head() has already fired.
        // A <style> tag inline in the output is the correct approach.
        $custom_css_output = '';
        $raw_css = trim($config['custom_css'] ?? '');
        if ($raw_css !== '') {
            $custom_css_output = sprintf(
                '<style id="wphz-carousel-css-%d">%s</style>',
                $id,
                $raw_css  // already sanitized via wp_strip_all_tags() on save
            );
        }

        return $custom_css_output . TemplateLoader::render_return('frontend/carousel-wrapper', [
            'id'       => $id,
            'items'    => $items,
            'sound'    => !empty($config['mute']) ? 'mute' : 'unmute',
            'slide'    => $config['direction'] ?? 'ltr',
            'is_muted' => !empty($config['mute']),
        ]);
    }

    /**
     * Decode product_ids JSON and attach WC product objects to each item.
     *
     * @param  array<int, array> $items
     * @return array<int, array>
     */
    private function hydrate_items(array $items): array {
        return array_map(function (array $item): array {
            $pids             = json_decode($item['product_ids'], true) ?: [];
            $item['products'] = $this->fetch_products($pids);
            return $item;
        }, $items);
    }

    /**
     * Resolve WC product objects, filtering out non-visible / non-existent products.
     *
     * @param  array $pids
     * @return array[] Associative array containing the WC_Product model and custom flags
     */
    private function fetch_products(array $pids): array {
        $products = [];
        foreach ($pids as $p_data) {
            $product_id = is_array($p_data) ? (int) ($p_data['id'] ?? 0) : (int) $p_data;
            $hide_atc   = is_array($p_data) && !empty($p_data['hide_atc']);

            $product = wc_get_product($product_id);
            if ($product) {
                $products[] = [
                    'model'    => $product,
                    'hide_atc' => $hide_atc,
                ];
            }
        }
        return $products;
    }
}