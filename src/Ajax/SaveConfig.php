<?php
namespace WPHZ\UGC\Ajax;
defined('ABSPATH') || exit;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\NonceHelper;
use WPHZ\UGC\Helpers\SanitizeHelper;
use WPHZ\UGC\Repository\CarouselRepository;

class SaveConfig extends AbstractSingleton {

    public function init(): void {
        add_action('wp_ajax_wphz_ugc_save_config', [$this, 'handle']);
    }

    public function handle(): void {
        NonceHelper::verify('wphz_ugc_admin');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.'], 403);
        }

        $config = [
            'name'           => SanitizeHelper::text($_POST['name']       ?? 'New Carousel'),
            'mute'           => !empty($_POST['mute']) && $_POST['mute'] === '1',
            'direction'      => in_array($_POST['direction'] ?? '', ['ltr', 'rtl'], true)
                                    ? $_POST['direction']
                                    : 'ltr',
            'hide_atc'       => isset($_POST['hide_atc']) ? (int) $_POST['hide_atc'] : 0,
        ];

        $carousel_id = (int) ($_POST['carousel_id'] ?? 0);

        if ($carousel_id > 0) {
            CarouselRepository::instance()->update($carousel_id, $config);
            wp_send_json_success(['message' => __('Configuration saved.', 'wphz-ugc')]);
        } else {
            wp_send_json_error(['message' => __('Invalid Carousel ID.', 'wphz-ugc')]);
        }
    }
}
