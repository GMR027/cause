<?php

declare(strict_types=1);

namespace Cauce\Helpers;

use RuntimeException;

/**
 * Caché de archivo simple para agregados y resultados costosos.
 *
 * Es local al servidor. Si en el futuro hay varias instancias, se sustituye
 * por Redis o Memcached sin cambiar a los consumidores.
 */
final class Cache
{
    private function __construct()
    {
    }

    public static function get(string $key): mixed
    {
        $path = self::path($key);

        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);

        if ($raw === false || $raw === '') {
            return null;
        }

        $payload = json_decode($raw, true);

        if (!is_array($payload) || !array_key_exists('value', $payload)) {
            return null;
        }

        if ((int) ($payload['expires_at'] ?? 0) < time()) {
            @unlink($path);

            return null;
        }

        return $payload['value'];
    }

    public static function put(string $key, mixed $value, int $ttl): void
    {
        $directory = self::directory();

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo crear el directorio de caché.');
        }

        $payload = json_encode([
            'expires_at' => time() + max(1, $ttl),
            'value' => $value,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            return;
        }

        $path = self::path($key);
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (@file_put_contents($temporary, $payload) === false) {
            return;
        }

        if (!@rename($temporary, $path)) {
            @unlink($temporary);
        }
    }

    /**
     * @param callable(): mixed $callback
     */
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = self::get($key);

        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        self::put($key, $value, $ttl);

        return $value;
    }

    public static function forget(string $key): void
    {
        $path = self::path($key);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function flush(): void
    {
        $directory = self::directory();

        if (!is_dir($directory)) {
            return;
        }

        foreach (glob($directory . '/*.json') ?: [] as $file) {
            @unlink($file);
        }
    }

    private static function directory(): string
    {
        return base_path('storage/cache');
    }

    private static function path(string $key): string
    {
        return self::directory() . '/' . hash('sha256', $key) . '.json';
    }
}
