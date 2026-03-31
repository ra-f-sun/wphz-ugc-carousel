<?php
namespace WPHZ\UGC\Admin;
defined('ABSPATH') || exit;

use WPHZ\UGC\AbstractSingleton;

class AdminMenu extends AbstractSingleton {

    public function init(): void {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu(): void {
        add_menu_page(
            __('UGC Carousel', 'wphz-ugc'),
            __('UGC Carousel', 'wphz-ugc'),
            'manage_options',
            'wphz-ugc-carousel',
            [$this, 'render_page'],
            'dashicons-video-alt3',
            58
        );
    }

    public function render_page(): void {
        $action = sanitize_text_field($_GET['action'] ?? 'list');
        $id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($action === 'edit' && $id > 0) {
            $active_tab = sanitize_key($_GET['tab'] ?? 'config');
            \WPHZ\UGC\Helpers\TemplateLoader::render('admin/layout', [
                'id'         => $id,
                'active_tab' => $active_tab,
            ]);
        } else {
            $carousels = \WPHZ\UGC\Repository\CarouselRepository::instance()->get_all();
            \WPHZ\UGC\Helpers\TemplateLoader::render('admin/list-view', [
                'carousels' => $carousels,
            ]);
        }
    }
}
