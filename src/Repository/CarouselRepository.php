<?php
namespace WPHZ\UGC\Repository;

use WPHZ\UGC\AbstractSingleton;

class CarouselRepository extends AbstractSingleton {

    private function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'wphz_ugc_carousels';
    }

    /** @return array<int, array> */
    public function get_all(): array {
        global $wpdb;
        $table = $this->table();
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A);
        return $rows ?: [];
    }

    public function get_by_id(int $id): ?array {
        global $wpdb;
        $table = $this->table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        ) ?: null;
    }

    public function insert(array $data): int|false {
        global $wpdb;
        $result = $wpdb->insert($this->table(), [
            'name'           => sanitize_text_field($data['name'] ?? 'New Carousel'),
            'heading'        => sanitize_text_field($data['heading'] ?? ''),
            'subheading'     => sanitize_text_field($data['subheading'] ?? ''),
            'mute'           => isset($data['mute']) ? (int) $data['mute'] : 1,
            'direction'      => sanitize_text_field($data['direction'] ?? 'ltr'),
            'on_arrow_right' => sanitize_text_field($data['on_arrow_right'] ?? ''),
            'on_arrow_left'  => sanitize_text_field($data['on_arrow_left'] ?? ''),
        ]);
        return $result ? $wpdb->insert_id : false;
    }

    public function update(int $id, array $data): bool {
        global $wpdb;
        $fields = [];
        if (isset($data['name']))           $fields['name']           = sanitize_text_field($data['name']);
        if (isset($data['heading']))        $fields['heading']        = sanitize_text_field($data['heading']);
        if (isset($data['subheading']))     $fields['subheading']     = sanitize_text_field($data['subheading']);
        if (isset($data['mute']))           $fields['mute']           = (int) $data['mute'];
        if (isset($data['direction']))      $fields['direction']      = sanitize_text_field($data['direction']);
        if (isset($data['on_arrow_right'])) $fields['on_arrow_right'] = sanitize_text_field($data['on_arrow_right']);
        if (isset($data['on_arrow_left']))  $fields['on_arrow_left']  = sanitize_text_field($data['on_arrow_left']);
        
        if (empty($fields)) return false;
        
        return (bool) $wpdb->update($this->table(), $fields, ['id' => $id]);
    }

    public function delete(int $id): bool {
        global $wpdb;
        return (bool) $wpdb->delete($this->table(), ['id' => $id]);
    }
}
