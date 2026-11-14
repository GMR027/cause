<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Helpers\Logger;
use PDO;
use Throwable;

/**
 * Analytics propio y anónimo para el panel de administración.
 *
 * Guarda el user_id internamente para calcular cohortes y activos, pero el
 * panel solo expone agregados con mínimo de usuarios. Nunca se guardan montos,
 * notas, correos ni contenido de archivos.
 */
final class AnalyticsService
{
    private const ALLOWED_CONTEXT = ['path', 'feature', 'tipo', 'version', 'source'];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @param array<string, mixed>|null $user
     * @param array<string, scalar|null> $context
     */
    public function track(string $event, array $context = [], ?array $user = null): void
    {
        if ($user !== null && (int) ($user['analytics_opt_out'] ?? 0) === 1) {
            return;
        }

        $safe = [];

        foreach (self::ALLOWED_CONTEXT as $key) {
            if (array_key_exists($key, $context) && $context[$key] !== null) {
                $safe[$key] = mb_substr((string) $context[$key], 0, 120);
            }
        }

        try {
            $statement = $this->db->prepare(
                'INSERT INTO analytics_eventos (user_id, evento, contexto_json, created_at)
                 VALUES (?, ?, ?, UTC_TIMESTAMP())'
            );
            $statement->execute([
                $user !== null ? (int) $user['id'] : null,
                mb_substr($event, 0, 60),
                $safe === [] ? null : json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable $exception) {
            Logger::warning('No se pudo registrar el evento de analytics', [
                'event' => $event,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $user
     */
    public function trackPath(array $user, string $path): void
    {
        if ((int) ($user['analytics_opt_out'] ?? 0) === 1) {
            return;
        }

        if (str_starts_with($path, '/admin') || str_starts_with($path, '/api')
            || str_starts_with($path, '/assets') || str_starts_with($path, '/desbloquear')
            || str_starts_with($path, '/legal')
        ) {
            return;
        }

        $feature = self::featureForPath($path);
        $context = ['path' => $path];

        if ($feature !== null) {
            $context['feature'] = $feature;
        }

        $this->track('page_view', $context, $user);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function setOptOut(array $user, bool $optOut): void
    {
        (new \Cauce\Repositories\UserRepository())->setAnalyticsOptOut((int) $user['id'], $optOut);

        if ($optOut) {
            $statement = $this->db->prepare('DELETE FROM analytics_eventos WHERE user_id = ?');
            $statement->execute([(int) $user['id']]);
        }

        \Cauce\Helpers\Cache::forget('admin_stats_v2');
    }

    public function prune(): void
    {
        $days = max(30, (int) config('security.analytics.retention_days', 365));

        try {
            $this->db->exec(
                'DELETE FROM analytics_eventos WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . $days . ' DAY)'
            );
        } catch (Throwable $exception) {
            Logger::warning('No se pudieron depurar los eventos de analytics', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public static function featureForPath(string $path): ?string
    {
        $map = [
            '/calendario' => 'calendario',
            '/ahorro' => 'metas',
            '/deudas' => 'deudas',
            '/deseos' => 'deseos',
            '/estado-cuenta' => 'estado_cuenta',
            '/inversion' => 'inversion',
            '/donacion' => 'donacion',
            '/logros' => 'logros',
            '/resumen' => 'resumen',
            '/movimientos' => 'movimientos',
            '/reportes' => 'reportes',
            '/buscar' => 'busqueda',
        ];

        foreach ($map as $prefix => $feature) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $feature;
            }
        }

        return null;
    }
}
