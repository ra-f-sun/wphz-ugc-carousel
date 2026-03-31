<?php
namespace WPHZ\UGC\Helpers;
defined('ABSPATH') || exit;

class TemplateLoader {

    /**
     * Render a template by name, extracting $data into local scope. Outputs directly.
     *
     * @param string       $template  Template path relative to the templates/ dir, without .php extension.
     * @param array<mixed> $data      Variables to extract into template scope.
     */
    public static function render(string $template, array $data = []): void {
        $file = WPHZ_UGC_TEMPLATES . ltrim($template, '/') . '.php';
        if (!file_exists($file)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
            trigger_error("WPHZ UGC: Template not found: {$file}", E_USER_WARNING);
            return;
        }
        extract($data, EXTR_SKIP); // EXTR_SKIP: never overwrite existing vars
        include $file;
    }

    /**
     * Same as render() but captures and returns output as a string.
     *
     * @param string       $template
     * @param array<mixed> $data
     */
    public static function render_return(string $template, array $data = []): string {
        ob_start();
        self::render($template, $data);
        return (string) ob_get_clean();
    }
}
