<?php

declare(strict_types=1);

namespace Cauce\Helpers;

/**
 * Token CSRF por sesión.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    private function __construct()
    {
    }

    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            Session::start();
        }

        if (!isset($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::KEY];
    }

    public static function rotate(): void
    {
        unset($_SESSION[self::KEY]);
        self::token();
    }

    public static function validate(?string $token): bool
    {
        return is_string($token)
            && $token !== ''
            && hash_equals(self::token(), $token);
    }
}
