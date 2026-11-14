<?php

declare(strict_types=1);

namespace Cauce\Helpers;

/**
 * Hash y verificación de contraseñas y respuestas de seguridad.
 */
final class PasswordHasher
{
    private function __construct()
    {
    }

    public static function hash(string $value): string
    {
        return password_hash($value, self::algorithm());
    }

    public static function verify(string $value, string $hash): bool
    {
        return password_verify($value, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::algorithm());
    }

    private static function algorithm(): string|int
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }
}
