<?php
namespace WPHZ\UGC\Ajax;
defined('ABSPATH') || exit;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\NonceHelper;
use WPHZ\UGC\Repository\ItemRepository;

class ExportCsv extends AbstractSingleton {

    public function init(): void {
        add_action('wp_ajax_wphz_ugc_export_csv', [$this, 'handle']);
    }

    public function handle(): void {
        NonceHelper::verify('wphz_ugc_admin');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized', '', ['response' => 403]);
        }

        $carousel_id = (int) ($_GET['carousel_id'] ?? 0);
        if ($carousel_id <= 0) {
            wp_die('Invalid carousel ID.');
        }

        $items    = ItemRepository::instance()->get_all((string) $carousel_id);
        $filename = 'carousel-' . $carousel_id . '-' . gmdate('Y-m-d') . '.csv';

        // Clear any buffered output before sending file headers
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['sort_order', 'video_url_hd', 'video_url_sd', 'poster_url', 'products']);

        foreach ($items as $item) {
            $pids_raw    = json_decode($item['product_ids'], true) ?: [];
            $products_export = [];

            foreach ($pids_raw as $p) {
                $p_id    = (int) ($p['id'] ?? 0);
                $product = $p_id ? wc_get_product($p_id) : null;
                $sku     = $product ? $product->get_sku() : '';

                // hide_atc: null = inherit, 0 = force show, 1 = force hide
                $hide_atc = array_key_exists('hide_atc', $p) ? $p['hide_atc'] : null;

                $products_export[] = [
                    'sku'      => $sku,
                    'id'       => $p_id,
                    'hide_atc' => $hide_atc,
                ];
            }

            fputcsv($out, [
                $item['sort_order'],
                $item['video_url_hd'] ?? '',
                $item['video_url_sd'] ?? '',
                $item['poster_url']   ?? '',
                wp_json_encode($products_export),
            ]);
        }

        fclose($out);
        exit;
    }
}
