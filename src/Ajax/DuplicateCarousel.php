<?php
namespace WPHZ\UGC\Ajax;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\NonceHelper;
use WPHZ\UGC\Repository\CarouselRepository;
use WPHZ\UGC\Repository\ItemRepository;

class DuplicateCarousel extends AbstractSingleton {

    public function init(): void {
        // We use admin-ajax.php but it's fundamentally a GET redirect hook
        add_action('wp_ajax_wphz_ugc_duplicate_carousel', [$this, 'handle']);
    }

    public function handle(): void {
        // Specifically look for 'wphz_nonce' as provided by wp_nonce_url
        NonceHelper::verify('wphz_ugc_admin', 'wphz_nonce');

        $carousel_id = (int) ($_GET['id'] ?? 0);
        if ($carousel_id <= 0) {
            wp_die(esc_html__('Invalid Carousel ID.', 'wphz-ugc'));
        }

        $repo     = CarouselRepository::instance();
        $carousel = $repo->get_by_id($carousel_id);

        if (!$carousel) {
            wp_die(esc_html__('Carousel not found.', 'wphz-ugc'));
        }

        // Duplicate the parent carousel configuration exactly
        $new_id = $repo->insert([
            'name'           => $carousel['name'] . ' (Copy)',
            'heading'        => $carousel['heading'],
            'subheading'     => $carousel['subheading'],
            'mute'           => $carousel['mute'],
            'direction'      => $carousel['direction'],
            'on_arrow_right' => $carousel['on_arrow_right'],
            'on_arrow_left'  => $carousel['on_arrow_left'],
            'custom_css'     => $carousel['custom_css'],
        ]);

        if (!$new_id) {
            wp_die(esc_html__('Failed to duplicate carousel.', 'wphz-ugc'));
        }

        // Deep clone the nested video/product item arrays
        $item_repo = ItemRepository::instance();
        $items     = $item_repo->get_all((string) $carousel_id);

        foreach ($items as $item) {
            // Data maps back cleanly because product_ids encodes into a JSON string natively within get_all
            $product_ids = json_decode($item['product_ids'], true) ?: [];

            $item_repo->insert([
                'carousel_id'  => (string) $new_id, // Map it cleanly back directly to the new parent
                'sort_order'   => $item['sort_order'],
                'video_id'     => $item['video_id'],
                'video_url_hd' => $item['video_url_hd'],
                'video_url_sd' => $item['video_url_sd'],
                'product_ids'  => $product_ids,
            ]);
        }

        // Redirect immediately seamlessly into the newly cloned configuration environment
        wp_redirect(admin_url('admin.php?page=wphz-ugc-carousel&action=edit&id=' . $new_id));
        exit;
    }
}
