<?php

declare(strict_types=1);

namespace Cauce\Helpers;

/**
 * Cabeceras de seguridad comunes.
 *
 * CSP se deja fuera por ahora: las vistas usan scripts inline para evitar el
 * flash de tema. El resto de cabeceras no rompe la aplicación.
 */
final class SecurityHeaders
{
    private function __construct()
    {
    }

    public static function apply(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        header('Cross-Origin-Opener-Policy: same-origin');

        if (Client::isHttps() && (string) config('app.env', 'production') === 'production') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
