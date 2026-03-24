<?php
namespace WPHZ\UGC\Installer;

class Installer {

    public static function activate(): void {
        self::create_tables();
        self::migrate_to_multi_carousel();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    private static function create_tables(): void {
        global $wpdb;
        $table_items     = $wpdb->prefix . 'wphz_ugc_items';
        $table_carousels = $wpdb->prefix . 'wphz_ugc_carousels';
        $charset         = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql_items = "CREATE TABLE {$table_items} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            carousel_id varchar(64) NOT NULL DEFAULT 'default',
            sort_order int(11) NOT NULL DEFAULT 0,
            video_id bigint(20) unsigned NOT NULL,
            video_url_hd text DEFAULT NULL,
            video_url_sd text DEFAULT NULL,
            product_ids longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY carousel_id (carousel_id),
            KEY sort_order (sort_order)
        ) {$charset};";

        $sql_carousels = "CREATE TABLE {$table_carousels} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            heading varchar(255),
            subheading varchar(255),
            mute tinyint(1) DEFAULT 1,
            direction varchar(10) DEFAULT 'ltr',
            on_arrow_right varchar(255),
            on_arrow_left varchar(255),
            custom_css longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) {$charset};";

        dbDelta($sql_items);
        dbDelta($sql_carousels);

        // Safe migration: add custom_css column to existing installs
        if ( ! $wpdb->get_var("SHOW COLUMNS FROM {$table_carousels} LIKE 'custom_css'") ) {
            $wpdb->query("ALTER TABLE {$table_carousels} ADD COLUMN custom_css longtext DEFAULT NULL");
        }

        // Safe migration: Port old `video_url` data to `video_url_hd` and explicitly drop the old column
        if ($wpdb->get_var("SHOW COLUMNS FROM {$table_items} LIKE 'video_url'")) {
            $wpdb->query("UPDATE {$table_items} SET video_url_hd = video_url WHERE video_url_hd IS NULL OR video_url_hd = ''");
            $wpdb->query("ALTER TABLE {$table_items} DROP COLUMN video_url");
        }

        update_option('wphz_ugc_db_version', WPHZ_UGC_VERSION);
    }

    private static function migrate_to_multi_carousel(): void {
        global $wpdb;
        $table_items     = $wpdb->prefix . 'wphz_ugc_items';
        $table_carousels = $wpdb->prefix . 'wphz_ugc_carousels';

        // Check if carousels table is empty
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_carousels}");
        if ($count > 0) {
            return; // Already migrated or populated
        }

        // Read old config
        $old_config = get_option('wphz_ugc_config');
        if (!$old_config) {
            return; // New install, nothing to migrate
        }

        $config = json_decode($old_config, true) ?: [];
        $danger = json_decode(get_option('wphz_ugc_danger_config') ?: '{}', true) ?: [];

        // Insert default carousel
        $wpdb->insert($table_carousels, [
            'id'             => 1,
            'name'           => 'Default Carousel',
            'heading'        => $config['heading'] ?? '',
            'subheading'     => $config['subheading'] ?? '',
            'mute'           => isset($config['mute']) ? (int) $config['mute'] : 1,
            'direction'      => $config['direction'] ?? 'ltr',
            'on_arrow_right' => $danger['on_arrow_right'] ?? '',
            'on_arrow_left'  => $danger['on_arrow_left'] ?? '',
        ]);

        // Update old items to point to id '1'
        $wpdb->update($table_items, ['carousel_id' => '1'], ['carousel_id' => 'default']);

        // Clean up old options
        delete_option('wphz_ugc_config');
        delete_option('wphz_ugc_danger_config');
    }
}
