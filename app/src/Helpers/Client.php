<?php

declare(strict_types=1);

namespace Cauce\Helpers;

/**
 * Datos del cliente HTTP: UA, IP y detección de escritorio.
 *
 * La detección de escritorio es deliberadamente conservadora: el backend no
 * puede conocer el ancho de la ventana, así que bloquea UA móviles/tablet y la
 * interfaz exige además un viewport de escritorio.
 */
final class Client
{
    private function __construct()
    {
    }

    public static function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public static function ip(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        return is_string($ip) && $ip !== '' ? mb_substr($ip, 0, 45) : null;
    }

    public static function isMobile(): bool
    {
        $ua = self::userAgent();

        if ($ua === '') {
            return false;
        }

        return preg_match(
            '/android|iphone|ipod|ipad|windows phone|iemobile|mobile|tablet|kindle|silk|opera mini|opera mobi|webos|blackberry|playbook/i',
            $ua
        ) === 1;
    }

    public static function isDesktop(): bool
    {
        return !self::isMobile();
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public static function wantsJson(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch'
            || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
    }
}
