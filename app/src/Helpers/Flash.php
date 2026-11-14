<?php

declare(strict_types=1);

namespace Cauce\Helpers;

/**
 * Mensajes flash entre redirecciones.
 */
final class Flash
{
    private const KEY = '_flash_messages';

    private function __construct()
    {
    }

    public static function success(string $key, array $params = []): void
    {
        self::set('success', $key, $params);
    }

    public static function error(string $key, array $params = []): void
    {
        self::set('danger', $key, $params);
    }

    public static function warning(string $key, array $params = []): void
    {
        self::set('warning', $key, $params);
    }

    public static function info(string $key, array $params = []): void
    {
        self::set('info', $key, $params);
    }

    public static function set(string $type, string $key, array $params = []): void
    {
        $_SESSION[self::KEY][] = [
            'type' => $type,
            'key' => $key,
            'params' => $params,
        ];
    }

    /**
     * @return list<array{type: string, key: string, params: array<string, string>}>
     */
    public static function pull(): array
    {
        $messages = $_SESSION[self::KEY] ?? [];
        unset($_SESSION[self::KEY]);

        return is_array($messages) ? $messages : [];
    }
}
