<?php

/**
 * bin/publicar_android.php — registra un APK firmado de la app Android.
 *
 * Uso (en el servidor o en el pod, con acceso a la base de datos):
 *   php bin/publicar_android.php <ruta.apk> <versión> ["notas de la versión"]
 *
 * Copia el APK a storage/android/ (volumen persistente) y lo registra en
 * app_android_versiones. La versión debe ser MAYOR que la última publicada:
 * Android no instala una «actualización» con un número menor.
 */

declare(strict_types=1);

use Cauce\Helpers\Logger;
use Cauce\Services\AndroidAppService;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/app/config/env.php';

$config = require BASE_PATH . '/app/config/app.php';
Logger::boot((string) env('LOG_PATH', 'storage/logs/app.log'), 'warning');

[, $apk, $version] = array_pad($argv, 3, '');
$notas = (string) ($argv[3] ?? '');

if ($apk === '' || $version === '') {
    fwrite(STDERR, "Uso: php bin/publicar_android.php <ruta.apk> <versión> [\"notas\"]\n");
    exit(2);
}

try {
    $publicada = (new AndroidAppService())->publish($apk, $version, $notas);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

$destino = AndroidAppService::directory() . '/' . $publicada['archivo'];
@chown($destino, 'www-data');
@chgrp($destino, 'www-data');

printf(
    "Publicado: %s (%d bytes, código %d)\nSHA-256: %s\n",
    $publicada['archivo'],
    (int) $publicada['tamano'],
    (int) $publicada['version_code'],
    $publicada['sha256']
);
