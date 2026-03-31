<?php
defined('ABSPATH') || exit;
namespace WPHZ\UGC\Ajax;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\NonceHelper;
use WPHZ\UGC\Repository\CarouselRepository;

class SaveCustomCss extends AbstractSingleton {

    public function init(): void {
        add_action('wp_ajax_wphz_ugc_save_custom_css', [$this, 'handle']);
    }

    public function handle(): void {
        NonceHelper::verify('wphz_ugc_admin');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.'], 403);
        }

        $carousel_id = (int) ($_POST['carousel_id'] ?? 0);
        if ($carousel_id <= 0) {
            wp_send_json_error(['message' => __('Invalid Carousel ID.', 'wphz-ugc')]);
            return; // Explicit — do not rely on wp_die() inside wp_send_json_error()
        }

        // wp_strip_all_tags: removes </style> injection and all HTML tags.
        // wp_unslash: reverses WordPress magic-quotes applied to $_POST.
        $css = wp_strip_all_tags(wp_unslash($_POST['custom_css'] ?? ''));

        $updated = CarouselRepository::instance()->update($carousel_id, [
            'custom_css' => $css,
        ]);

        if ($updated) {
            wp_send_json_success(['message' => __('Custom CSS saved.', 'wphz-ugc')]);
        } else {
            wp_send_json_error(['message' => __('Failed to save.', 'wphz-ugc')]);
        }
    }
}