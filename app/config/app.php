<?php

declare(strict_types=1);

/*
 * Configuración general de la aplicación.
 *
 * Devuelve un arreglo con los valores del entorno ya normalizados. Es seguro
 * incluir este archivo desde cualquier punto de entrada.
 */

$basePath = dirname(__DIR__, 2);

return [
    'name' => (string) env('APP_NAME', 'Cauce'),
    'env' => (string) env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => rtrim((string) env('APP_URL', 'http://localhost:8000'), '/'),
    'timezone' => (string) env('APP_TIMEZONE', 'UTC'),
    'share_url' => (string) env('APP_SHARE_URL', ''),
    'version' => (string) env('APP_VERSION', '0.0.0'),
    'mail_enabled' => (bool) env('MAIL_ENABLED', false),
    'paths' => [
        'base' => $basePath,
        'app' => $basePath . '/app',
        'views' => $basePath . '/app/views',
        'storage' => $basePath . '/storage',
        'logs' => $basePath . '/storage/logs',
        'public' => $basePath . '/public',
    ],
];
