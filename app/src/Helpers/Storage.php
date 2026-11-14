<?php

declare(strict_types=1);

namespace Cauce\Helpers;

use RuntimeException;

/**
 * Operaciones de archivos fuera de public/.
 */
final class Storage
{
    private function __construct()
    {
    }

    public static function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('No se pudo crear el directorio de almacenamiento.');
        }
    }

    public static function deleteDirectory(string $path): void
    {
        if ($path === '' || !is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $absolute = rtrim($path, '/') . '/' . $item;

            if (is_dir($absolute) && !is_link($absolute)) {
                self::deleteDirectory($absolute);
                continue;
            }

            @unlink($absolute);
        }

        @rmdir($path);
    }

    public static function directorySize(string $path): int
    {
        if ($path === '' || !is_dir($path)) {
            return 0;
        }

        $total = 0;

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $absolute = rtrim($path, '/') . '/' . $item;

            if (is_dir($absolute) && !is_link($absolute)) {
                $total += self::directorySize($absolute);
                continue;
            }

            $size = @filesize($absolute);
            $total += $size === false ? 0 : (int) $size;
        }

        return $total;
    }

    public static function fileCount(string $path): int
    {
        if ($path === '' || !is_dir($path)) {
            return 0;
        }

        $count = 0;

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $absolute = rtrim($path, '/') . '/' . $item;

            if (is_dir($absolute) && !is_link($absolute)) {
                $count += self::fileCount($absolute);
                continue;
            }

            $count++;
        }

        return $count;
    }
}
