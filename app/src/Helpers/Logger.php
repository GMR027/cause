<?php

declare(strict_types=1);

namespace Cauce\Helpers;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Fachada de logging sobre Monolog.
 *
 * Toda la aplicación escribe a través de esta clase para no acoplarse
 * directamente a Monolog y garantizar un destino único (LOG_PATH).
 */
final class Logger
{
    private static ?LoggerInterface $logger = null;

    private function __construct()
    {
    }

    public static function boot(string $path, string $level = 'debug'): void
    {
        $resolved = self::resolvePath($path);
        $directory = dirname($resolved);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('No se pudo crear el directorio de logs: %s', $directory));
        }

        $handler = new StreamHandler($resolved, self::level($level));
        $handler->setFormatter(new LineFormatter(null, null, true, true));

        $logger = new MonologLogger('cauce');
        $logger->pushHandler($handler);
        $logger->pushProcessor(new PsrLogMessageProcessor());

        self::$logger = $logger;
    }

    public static function instance(): LoggerInterface
    {
        if (self::$logger === null) {
            self::boot('storage/logs/app.log');
        }

        return self::$logger;
    }

    public static function debug(string $message, array $context = []): void
    {
        self::instance()->debug($message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::instance()->info($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::instance()->warning($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::instance()->error($message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::instance()->critical($message, $context);
    }

    private static function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }

    private static function level(string $level): Level
    {
        return match (strtolower($level)) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'notice' => Level::Notice,
            'warning', 'warn' => Level::Warning,
            'error' => Level::Error,
            'critical' => Level::Critical,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
            default => Level::Debug,
        };
    }
}
