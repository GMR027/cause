<?php

declare(strict_types=1);

namespace Cauce\Config;

use Cauce\Helpers\Logger;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Conexión PDO única (singleton) a MySQL.
 *
 * No contiene lógica de negocio: solo construye y comparte la conexión, y
 * expone un test de conectividad para la pantalla de Fase 0.
 *
 * Punto único de sustitución para SQLCipher: si en el futuro se migra a una
 * base cifrada, el DSN y las opciones se cambian aquí y los repositorios
 * siguen usando la misma interfaz PDO.
 */
final class Database
{
    private static ?PDO $connection = null;

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public function __wakeup(): void
    {
        throw new RuntimeException('La conexión de base de datos no es serializable.');
    }

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            (string) env('DB_HOST', '127.0.0.1'),
            (string) env('DB_PORT', '3306'),
            (string) env('DB_DATABASE', 'cauce'),
            (string) env('DB_CHARSET', 'utf8mb4')
        );

        try {
            self::$connection = new PDO(
                $dsn,
                (string) env('DB_USERNAME', ''),
                (string) env('DB_PASSWORD', ''),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                    PDO::ATTR_PERSISTENT => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
                ]
            );
        } catch (PDOException $exception) {
            Logger::error('No se pudo establecer la conexión MySQL', [
                'driver_code' => $exception->errorInfo[1] ?? null,
                'sql_state' => $exception->errorInfo[0] ?? null,
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException('No se pudo conectar con MySQL.', 0, $exception);
        }

        Logger::debug('Conexión PDO establecida', [
            'database' => (string) env('DB_DATABASE', ''),
        ]);

        return self::$connection;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public static function testConnection(): array
    {
        try {
            $pdo = self::connection();
            $version = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            $database = (string) env('DB_DATABASE', '');

            Logger::info('Test de conexión MySQL correcto', [
                'database' => $database,
                'server_version' => $version,
            ]);

            return [
                'ok' => true,
                'message' => sprintf(
                    'Conexión exitosa a MySQL %s, base de datos "%s".',
                    $version,
                    $database
                ),
            ];
        } catch (Throwable $exception) {
            $message = self::friendlyMessage($exception);

            Logger::warning('Test de conexión MySQL fallido', [
                'message' => $exception->getMessage(),
                'driver_code' => $exception->getPrevious() instanceof PDOException
                    ? ($exception->getPrevious()->errorInfo[1] ?? null)
                    : null,
            ]);

            return ['ok' => false, 'message' => $message];
        }
    }

    private static function friendlyMessage(Throwable $exception): string
    {
        $previous = $exception->getPrevious();
        $driverCode = $previous instanceof PDOException
            ? (int) ($previous->errorInfo[1] ?? 0)
            : 0;

        return match ($driverCode) {
            1045 => 'Acceso denegado: revisa DB_USERNAME y DB_PASSWORD en tu .env.',
            1049 => sprintf(
                'La base de datos "%s" no existe. Ejecuta: mysql -u root -p < sql/schema.sql',
                (string) env('DB_DATABASE', 'cauce')
            ),
            2002, 2003 => 'El servidor MySQL no está disponible en DB_HOST:DB_PORT. ¿Está corriendo?',
            2006 => 'La conexión con MySQL se perdió. Intenta de nuevo.',
            default => 'No se pudo conectar con MySQL. Revisa las variables DB_* y storage/logs/app.log.',
        };
    }
}
