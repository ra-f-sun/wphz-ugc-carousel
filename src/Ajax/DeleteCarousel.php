<?php
namespace WPHZ\UGC\Ajax;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Repository\CarouselRepository;

class DeleteCarousel extends AbstractSingleton {

    public function init(): void {
        add_action('wp_ajax_wphz_ugc_delete_carousel', [$this, 'handle']);
    }

    public function handle(): void {
        if (!isset($_GET['wphz_nonce']) || !wp_verify_nonce($_GET['wphz_nonce'], 'wphz_ugc_admin')) {
            wp_die('Nonce verification failed.');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $id = (int) ($_GET['id'] ?? 0);
        
        if ($id > 0) {
            CarouselRepository::instance()->delete($id);
            
            // Cascade delete items
            global $wpdb;
            $table = $wpdb->prefix . 'wphz_ugc_items';
            $wpdb->delete($table, ['carousel_id' => (string) $id]);
        }

        wp_redirect(admin_url('admin.php?page=wphz-ugc-carousel&wphz_msg=deleted'));
        exit;
    }
}
