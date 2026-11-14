<?php

declare(strict_types=1);

use Dotenv\Dotenv;

/*
 * Carga y validación del entorno.
 *
 * Este archivo se incluye una sola vez desde public/index.php. Deja las
 * variables disponibles a través de la función env() definida en
 * app/src/Helpers/functions.php.
 */

$rootPath = dirname(__DIR__, 2);

if (!is_file($rootPath . '/vendor/autoload.php')) {
    throw new RuntimeException(
        'No se encontró vendor/autoload.php. Ejecuta "composer install" antes de arrancar Cauce.'
    );
}

if (is_file($rootPath . '/.env')) {
    Dotenv::createImmutable($rootPath)->load();
}

$required = [
    'APP_NAME',
    'APP_ENV',
    'APP_URL',
    'APP_TIMEZONE',
    'DB_HOST',
    'DB_PORT',
    'DB_DATABASE',
    'DB_USERNAME',
    'DB_CHARSET',
    'LOG_PATH',
];

$missing = array_values(array_filter(
    $required,
    static fn (string $key): bool => env($key) === null || env($key) === ''
));

if ($missing !== []) {
    throw new RuntimeException(
        'Faltan variables obligatorias en .env: ' . implode(', ', $missing) . '.'
    );
}

date_default_timezone_set((string) env('APP_TIMEZONE', 'UTC'));

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
