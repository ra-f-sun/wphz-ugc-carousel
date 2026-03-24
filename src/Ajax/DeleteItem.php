<?php
namespace WPHZ\UGC\Ajax;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\NonceHelper;
use WPHZ\UGC\Repository\ItemRepository;

class DeleteItem extends AbstractSingleton {

    public function init(): void {
        add_action('wp_ajax_wphz_ugc_delete_item', [$this, 'handle']);
    }

    public function handle(): void {
        NonceHelper::verify('wphz_ugc_admin');

        $id = (int) ($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error(['message' => 'Invalid item ID.']);
        }

        $ok = ItemRepository::instance()->delete($id);
        $ok ? wp_send_json_success() : wp_send_json_error(['message' => 'Item not found.']);
    }
}
