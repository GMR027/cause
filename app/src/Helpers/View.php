<?php

declare(strict_types=1);

namespace Cauce\Helpers;

use RuntimeException;
use Throwable;

/**
 * Render de vistas PHP con layout.
 */
final class View
{
    /** @var array<string, mixed> */
    private static array $shared = [];

    private function __construct()
    {
    }

    /**
     * Comparte datos con todas las vistas (config, usuario, idioma, etc.).
     *
     * @param array<string, mixed> $data
     */
    public static function share(array $data): void
    {
        self::$shared = array_merge(self::$shared, $data);
    }

    public static function render(string $view, array $data = [], string $layout = 'layouts/base'): string
    {
        $data = array_merge(self::$shared, $data);

        $viewsPath = dirname(__DIR__, 2) . '/views';
        $viewFile = $viewsPath . '/' . ltrim($view, '/') . '.php';

        if (!is_file($viewFile)) {
            throw new RuntimeException(sprintf('Vista no encontrada: %s', $view));
        }

        $content = self::capture($viewFile, $data);

        if ($layout === '') {
            return $content;
        }

        $layoutFile = $viewsPath . '/' . ltrim($layout, '/') . '.php';

        if (!is_file($layoutFile)) {
            throw new RuntimeException(sprintf('Layout no encontrado: %s', $layout));
        }

        return self::capture($layoutFile, array_merge($data, ['content' => $content]));
    }

    private static function capture(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);

        ob_start();

        try {
            require $file;
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }

        return (string) ob_get_clean();
    }
}
