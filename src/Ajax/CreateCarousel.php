<?php
namespace WPHZ\UGC\Ajax;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\NonceHelper;
use WPHZ\UGC\Helpers\SanitizeHelper;
use WPHZ\UGC\Repository\CarouselRepository;

class CreateCarousel extends AbstractSingleton {

    public function init(): void {
        add_action('admin_action_wphz_ugc_create_carousel', [$this, 'handle']);
    }

    public function handle(): void {
        NonceHelper::verify('wphz_ugc_admin');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $name = SanitizeHelper::text($_POST['name'] ?? 'New Carousel');
        
        $id = CarouselRepository::instance()->insert(['name' => $name]);

        if ($id) {
            wp_redirect(admin_url('admin.php?page=wphz-ugc-carousel&action=edit&id=' . $id . '&tab=config'));
            exit;
        }

        wp_die('Failed to create carousel.');
    }
}
