<?php

declare(strict_types=1);

namespace App\Core;

/**
 * PHP-Templates in /templates. render() setzt den Inhalt in ein Layout
 * (Standard: templates/layout.php, Verwaltung: templates/layout-admin.php).
 * Im Layout steht der Seiteninhalt als $content zur Verfügung.
 */
final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $content = self::partial($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial($layout, $data + ['content' => $content]);
    }

    /** @param array<string, mixed> $data */
    public static function partial(string $template, array $data = []): string
    {
        $file = dirname(__DIR__, 2) . '/templates/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Template fehlt: $template");
        }
        return (static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            try {
                include $__file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
            return (string) ob_get_clean();
        })($file, $data);
    }
}
