<?php
namespace WPHZ\UGC\Ajax;
defined('ABSPATH') || exit;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Helpers\NonceHelper;
use WPHZ\UGC\Repository\ItemRepository;

class ImportCsv extends AbstractSingleton {

    public function init(): void {
        add_action('wp_ajax_wphz_ugc_import_csv', [$this, 'handle']);
    }

    public function handle(): void {
        NonceHelper::verify('wphz_ugc_admin');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.'], 403);
        }

        $carousel_id = (int) ($_POST['carousel_id'] ?? 0);
        if ($carousel_id <= 0) {
            wp_send_json_error(['message' => 'Invalid carousel ID.']);
        }

        if (empty($_FILES['csv_file']['tmp_name']) || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
            wp_send_json_error(['message' => 'No valid file uploaded.']);
        }

        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) {
            wp_send_json_error(['message' => 'Cannot read uploaded file.']);
        }

        $header   = fgetcsv($handle);
        $expected = ['sort_order', 'video_url_hd', 'video_url_sd', 'poster_url', 'products'];
        if ($header !== $expected) {
            fclose($handle);
            wp_send_json_error([
                'message' => 'Invalid CSV format. Expected columns: ' . implode(', ', $expected),
            ]);
        }

        // Delete all existing items for this carousel (same pattern as SaveContent)
        global $wpdb;
        $table = $wpdb->prefix . 'wphz_ugc_items';
        $wpdb->delete($table, ['carousel_id' => (string) $carousel_id]);

        $repo     = ItemRepository::instance();
        $sort     = 0;
        $imported = 0;
        $errors   = [];

        while (($row = fgetcsv($handle)) !== false) {
            // Pad to 5 columns in case products column is missing
            $row = array_pad($row, 5, '');

            [, $video_url_hd, $video_url_sd, $poster_url, $products_json] = $row;

            $video_url_hd = esc_url_raw(trim($video_url_hd));
            $video_url_sd = esc_url_raw(trim($video_url_sd));
            $poster_url   = esc_url_raw(trim($poster_url));

            // Skip rows with no video at all
            if (!$video_url_hd && !$video_url_sd) {
                continue;
            }

            $product_ids = [];
            $row_errors  = [];

            if (!empty(trim($products_json))) {
                $parsed = json_decode(trim($products_json), true);
                if (is_array($parsed)) {
                    foreach ($parsed as $entry) {
                        $sku     = trim($entry['sku'] ?? '');
                        $id_hint = (int) ($entry['id'] ?? 0);

                        // Prefer SKU lookup; fall back to ID for products with no SKU
                        $resolved_id = 0;
                        if ($sku !== '') {
                            $resolved_id = (int) wc_get_product_id_by_sku($sku);
                        }
                        if (!$resolved_id && $id_hint > 0) {
                            $resolved_id = $id_hint;
                        }

                        if (!$resolved_id) {
                            $row_errors[] = 'Product SKU "' . esc_html($sku) . '" (id hint: ' . $id_hint . ') not found, skipped.';
                            continue;
                        }

                        // hide_atc: null = inherit, 0 = force show, 1 = force hide
                        $hide_raw = array_key_exists('hide_atc', $entry) ? $entry['hide_atc'] : null;
                        $hide_atc = ($hide_raw !== null) ? (int) $hide_raw : null;

                        $product_ids[] = ['id' => $resolved_id, 'hide_atc' => $hide_atc];
                    }
                }
            }

            if (!empty($row_errors)) {
                foreach ($row_errors as $err) {
                    $errors[] = 'Row ' . ($sort + 1) . ': ' . $err;
                }
            }

            if ($repo->insert([
                'carousel_id'  => (string) $carousel_id,
                'sort_order'   => $sort++,
                'video_id'     => 0,
                'video_url_hd' => $video_url_hd,
                'video_url_sd' => $video_url_sd,
                'poster_url'   => $poster_url,
                'product_ids'  => $product_ids,
            ])) {
                $imported++;
            } else {
                $errors[] = 'Row ' . $sort . ': database insert failed.';
            }
        }

        fclose($handle);

        wp_send_json_success([
            'message'  => sprintf('%d item(s) imported successfully.', $imported),
            'imported' => $imported,
            'errors'   => $errors,
        ]);
    }
}
