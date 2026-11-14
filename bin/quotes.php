<?php

declare(strict_types=1);

use Cauce\Helpers\Logger;
use Cauce\Services\QuoteService;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/app/config/env.php';

$config = require BASE_PATH . '/app/config/app.php';
Logger::boot((string) env('LOG_PATH', 'storage/logs/app.log'), 'warning');

try {
    $result = (new QuoteService())->refreshAll();
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
} catch (Throwable $exception) {
    Logger::error('Falló la actualización de cotizaciones', [
        'error' => $exception->getMessage(),
    ]);
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
