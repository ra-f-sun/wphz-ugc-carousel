<?php
defined('ABSPATH') || exit;
namespace WPHZ\UGC\Admin;

use WPHZ\UGC\AbstractSingleton;
use WPHZ\UGC\Repository\CarouselRepository;

class ConfigPage extends AbstractSingleton {

    /**
     * Provides data to the config-tab template. No output here.
     *
     * @return array<string, mixed>
     */
    public function get_data(int $id): array {
        $config = CarouselRepository::instance()->get_by_id($id) ?: [];

        return [
            'id'             => $id,
            'name'           => $config['name']       ?? '',
            'heading'        => $config['heading']    ?? '',
            'subheading'     => $config['subheading'] ?? '',
            'mute'           => (bool) ($config['mute'] ?? true),
            'direction'      => $config['direction']  ?? 'ltr',
            'on_arrow_right' => $config['on_arrow_right'] ?? '',
            'on_arrow_left'  => $config['on_arrow_left']  ?? '',
            'custom_css'     => $config['custom_css']     ?? '',
        ];
    }
}
