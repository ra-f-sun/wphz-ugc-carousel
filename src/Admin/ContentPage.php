<?php
namespace WPHZ\UGC\Admin;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Repository\ItemRepository;

class ContentPage extends AbstractSingleton {

    /**
     * Provides data to the content-tab template. No output here.
     *
     * @return array<string, mixed>
     */
    public function get_data(int $id): array {
        $items = ItemRepository::instance()->get_all((string) $id);

        // Decode product_ids JSON for each item (C2 contract: always JSON, never comma-sep)
        $items = array_map(function (array $item): array {
            $item['product_ids'] = json_decode($item['product_ids'], true) ?: [];
            return $item;
        }, $items);

        return [
            'id'        => $id,
            'items'     => $items,
            'shortcode' => sprintf('[wphz_ugc_carousel id="%d"]', $id),
        ];
    }
}
