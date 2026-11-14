<?php

declare(strict_types=1);

use Cauce\Helpers\Csrf;
use Cauce\Helpers\I18n;
use Cauce\Helpers\Logger;
use Cauce\Helpers\Session;
use Cauce\Helpers\View;
use Cauce\Repositories\NotificationRepository;
use Cauce\Services\AuthService;
use Cauce\Services\ThemeService;

/**
 * Front controller de Cauce.
 *
 * Todo el tráfico HTTP entra por aquí (ver public/.htaccess y public/router.php).
 */

define('BASE_PATH', dirname(__DIR__));

$autoload = BASE_PATH . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');

    exit("Cauce: faltan las dependencias de PHP.\nEjecuta: composer install\n");
}

require $autoload;

$config = [];

set_exception_handler(static function (Throwable $exception) use (&$config): void {
    try {
        Logger::critical('Excepción no controlada', [
            'type' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    } catch (Throwable) {
        error_log(sprintf('[Cauce] %s: %s', $exception::class, $exception->getMessage()));
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    $debug = (bool) ($config['debug'] ?? false);

    try {
        echo View::render('errors/500', [
            'title' => 'Error interno',
            'message' => $debug
                ? $exception->getMessage()
                : 'Ocurrió un error inesperado. Revisa storage/logs/app.log para más detalles.',
            'trace' => $debug ? $exception->getTraceAsString() : null,
            'config' => $config,
        ]);
    } catch (Throwable) {
        echo '<h1>Error interno</h1><p>Revisa storage/logs/app.log.</p>';
    }
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

require BASE_PATH . '/app/config/env.php';

$config = require BASE_PATH . '/app/config/app.php';

Logger::boot(
    (string) env('LOG_PATH', 'storage/logs/app.log'),
    $config['debug'] ? 'debug' : 'warning'
);

Logger::info('Cauce iniciado', [
    'environment' => $config['env'],
    'version' => $config['version'],
    'php' => PHP_VERSION,
]);

\Cauce\Helpers\SecurityHeaders::apply();

Session::start();

$currentUser = AuthService::user();
$lang = I18n::boot($currentUser !== null ? (string) $currentUser['idioma'] : null);
$theme = (new ThemeService())->resolve($currentUser);
$unreadNotifications = 0;

if ($currentUser !== null) {
    try {
        $unreadNotifications = (new NotificationRepository())->unreadCount((int) $currentUser['id']);
    } catch (\Throwable) {
        $unreadNotifications = 0;
    }
}

View::share([
    'config' => $config,
    'currentUser' => $currentUser,
    'lang' => $lang,
    'locale' => \Cauce\Helpers\Locale::locale($lang),
    'jsI18n' => I18n::jsPayload(),
    'theme' => $theme,
    'unreadNotifications' => $unreadNotifications,
]);

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'POST' && !Csrf::validate(isset($_POST['_token']) && is_string($_POST['_token']) ? $_POST['_token'] : null)) {
    http_response_code(419);
    echo View::render('errors/419', ['title' => __('common.errors.419.title')]);
    exit;
}

/** @var Cauce\Helpers\Router $router */
$router = require base_path('app/routes/web.php');

$router->dispatch($method, $_SERVER['REQUEST_URI'] ?? '/');
