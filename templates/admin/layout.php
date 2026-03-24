<?php defined('ABSPATH') || exit; ?>
<div class="wrap wphz-ugc-admin">
    <a href="?page=wphz-ugc-carousel" class="page-title-action" style="margin-bottom: 10px; display: inline-block;">&larr; Back to All Carousels</a>
    <h1><?php esc_html_e('Edit UGC Carousel', 'wphz-ugc'); ?></h1>

    <nav class="nav-tab-wrapper">
        <a href="?page=wphz-ugc-carousel&action=edit&id=<?php echo esc_attr($id); ?>&tab=config"
           class="nav-tab <?php echo $active_tab === 'config' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Configuration', 'wphz-ugc'); ?>
        </a>
        <a href="?page=wphz-ugc-carousel&action=edit&id=<?php echo esc_attr($id); ?>&tab=content"
           class="nav-tab <?php echo $active_tab === 'content' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Content', 'wphz-ugc'); ?>
        </a>
        <a href="?page=wphz-ugc-carousel&action=edit&id=<?php echo esc_attr($id); ?>&tab=custom-css"
           class="nav-tab <?php echo $active_tab === 'custom-css' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Custom CSS', 'wphz-ugc'); ?>
        </a>
    </nav>

    <div class="tab-content">
        <?php if ($active_tab === 'config'): ?>
            <?php \WPHZ\UGC\Helpers\TemplateLoader::render(
                'admin/config-tab',
                \WPHZ\UGC\Admin\ConfigPage::instance()->get_data($id)
            ); ?>
        <?php elseif ($active_tab === 'content'): ?>
            <?php \WPHZ\UGC\Helpers\TemplateLoader::render(
                'admin/content-tab',
                \WPHZ\UGC\Admin\ContentPage::instance()->get_data($id)
            ); ?>
        <?php elseif ($active_tab === 'custom-css'): ?>
            <?php \WPHZ\UGC\Helpers\TemplateLoader::render(
                'admin/custom-css-tab',
                \WPHZ\UGC\Admin\ConfigPage::instance()->get_data($id)
            ); ?>
        <?php endif; ?>
    </div>
</div>
