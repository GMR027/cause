<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Helpers\Cache;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Estadísticas globales anónimas para el admin.
 *
 * Regla: nunca se devuelve un dato individual. Las tasas, promedios y
 * cohortes exigen al menos 10 usuarios; si no, se marca "datos insuficientes".
 */
final class AdminStatsService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $seconds = (int) config('security.admin_stats.cache_seconds', 3600);

        return Cache::remember('admin_stats_v2', $seconds, fn (): array => $this->calculate());
    }

    public function refresh(): void
    {
        Cache::forget('admin_stats_v2');
    }

    /**
     * @return array<string, mixed>
     */
    private function calculate(): array
    {
        $min = (int) config('security.admin_stats.min_group', 10);
        $totalUsers = (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $activeUsers = (int) $this->db->query("SELECT COUNT(*) FROM users WHERE estado = 'activo'")->fetchColumn();
        $onboarded = (int) $this->db->query('SELECT COUNT(*) FROM users WHERE onboarding_completado = 1')->fetchColumn();

        return [
            'generated_at' => gmdate('c'),
            'min_group' => $min,
            'users' => [
                'total' => $totalUsers,
                'active' => $activeUsers,
                'onboarded' => $onboarded,
                'active_7' => $this->distinctActive(7),
                'active_30' => $this->distinctActive(30),
                'new_per_day' => $this->newPerDay(),
            ],
            'savers' => $this->rate(
                "SELECT COUNT(DISTINCT a.user_id) FROM aportaciones_ahorro a
                 JOIN users u ON u.id = a.user_id AND u.estado = 'activo'
                 WHERE DATE_FORMAT(a.fecha, '%Y-%m') = DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m')",
                $activeUsers,
                $min
            ),
            'compliance' => $this->rate(
                "SELECT COUNT(*) FROM niveles n JOIN users u ON u.id = n.user_id AND u.estado = 'activo'
                 WHERE n.nivel_actual > 0 AND n.porcentaje_cumplimiento >= 70",
                (int) $this->db->query(
                    "SELECT COUNT(*) FROM niveles n JOIN users u ON u.id = n.user_id AND u.estado = 'activo'
                     WHERE n.nivel_actual > 0"
                )->fetchColumn(),
                $min
            ),
            'methods' => $this->methodDistribution($min),
            'debts' => $this->debtStats($min),
            'emergency' => $this->emergencyStats($min),
            'features' => $this->featureUsage($activeUsers, $min),
            'evolution' => $this->evolution($min),
            'retention' => $this->retention($min),
            'score' => null,
        ];
    }

    private function distinctActive(int $days): int
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(DISTINCT user_id) FROM analytics_eventos
             WHERE user_id IS NOT NULL AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)'
        );
        $statement->execute([$days]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    private function newPerDay(): array
    {
        $statement = $this->db->query(
            'SELECT DATE(created_at) AS d, COUNT(*) AS c FROM users
             WHERE created_at >= DATE_SUB(UTC_DATE(), INTERVAL 29 DAY)
             GROUP BY DATE(created_at) ORDER BY d ASC'
        );
        $counts = [];

        foreach ($statement->fetchAll() as $row) {
            $counts[(string) $row['d']] = (int) $row['c'];
        }

        $result = [];
        $today = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        for ($i = 29; $i >= 0; $i--) {
            $date = $today->modify('-' . $i . ' days')->format('Y-m-d');
            $result[] = ['date' => $date, 'count' => $counts[$date] ?? 0];
        }

        return $result;
    }

    /**
     * @return array{enough: bool, percent: ?float, count: int, denominator: int}
     */
    private function rate(string $countQuery, int $denominator, int $min): array
    {
        $count = (int) $this->db->query($countQuery)->fetchColumn();

        if ($denominator < $min) {
            return ['enough' => false, 'percent' => null, 'count' => $count, 'denominator' => $denominator];
        }

        return [
            'enough' => true,
            'percent' => round(($count / max(1, $denominator)) * 100, 1),
            'count' => $count,
            'denominator' => $denominator,
        ];
    }

    /**
     * @return array{enough: bool, total: int, items: list<array{name: string, count: int, percent: float}>}
     */
    private function methodDistribution(int $min): array
    {
        $statement = $this->db->query(
            "SELECT m.nombre, COUNT(*) AS c
             FROM usuario_metodos um
             JOIN metodos m ON m.id = um.metodo_id
             JOIN users u ON u.id = um.user_id AND u.estado = 'activo'
             WHERE um.vigente_hasta IS NULL
             GROUP BY m.id, m.nombre
             ORDER BY c DESC"
        );
        $rows = $statement->fetchAll();
        $total = array_sum(array_map(static fn (array $row): int => (int) $row['c'], $rows));

        if ($total < $min) {
            return ['enough' => false, 'total' => $total, 'items' => []];
        }

        $items = [];
        $others = 0;

        foreach ($rows as $row) {
            $count = (int) $row['c'];

            if ($count < $min) {
                $others += $count;
                continue;
            }

            $items[] = [
                'name' => (string) $row['nombre'],
                'count' => $count,
                'percent' => round(($count / $total) * 100, 1),
            ];
        }

        if ($others > 0) {
            $items[] = [
                'name' => __('admin.stats.others'),
                'count' => $others,
                'percent' => round(($others / $total) * 100, 1),
            ];
        }

        return ['enough' => true, 'total' => $total, 'items' => $items];
    }

    /**
     * @return array{enough: bool, users: int, percent: ?float, average: ?int}
     */
    private function debtStats(int $min): array
    {
        $users = (int) $this->db->query(
            'SELECT COUNT(DISTINCT user_id) FROM deudas WHERE activa = 1'
        )->fetchColumn();

        if ($users < $min) {
            return ['enough' => false, 'users' => $users, 'percent' => null, 'average' => null];
        }

        $active = (int) $this->db->query("SELECT COUNT(*) FROM users WHERE estado = 'activo'")->fetchColumn();
        $average = (int) round((float) $this->db->query(
            'SELECT AVG(saldo) FROM (
                SELECT user_id, SUM(saldo_actual_principal_centavos) AS saldo
                FROM deudas WHERE activa = 1 GROUP BY user_id
             ) t'
        )->fetchColumn());

        return [
            'enough' => true,
            'users' => $users,
            'percent' => $active > 0 ? round(($users / $active) * 100, 1) : null,
            'average' => $average,
        ];
    }

    /**
     * @return array{enough: bool, users: int, complete: int, percent: ?float, average_months: ?float}
     */
    private function emergencyStats(int $min): array
    {
        $statement = $this->db->query(
            "SELECT m.user_id, m.objetivo_centavos,
                (SELECT COALESCE(SUM(a.monto_principal_centavos), 0) FROM aportaciones_ahorro a WHERE a.meta_id = m.id)
                - (SELECT COALESCE(SUM(r.monto_principal_centavos), 0) FROM retiros_ahorro r WHERE r.meta_id = m.id) AS saved,
                (SELECT COALESCE(SUM(g.monto_principal_centavos), 0) / 3 FROM gastos g
                 WHERE g.user_id = m.user_id AND g.fecha >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 MONTH)) AS monthly
             FROM metas_ahorro m
             JOIN users u ON u.id = m.user_id AND u.estado = 'activo'
             WHERE m.activa = 1 AND m.es_emergencia = 1"
        );
        $rows = $statement->fetchAll();
        $userIds = array_values(array_unique(array_map(static fn (array $row): int => (int) $row['user_id'], $rows)));
        $users = count($userIds);

        if ($users < $min) {
            return ['enough' => false, 'users' => $users, 'complete' => 0, 'percent' => null, 'average_months' => null];
        }

        $completeUsers = [];
        $months = [];

        foreach ($rows as $row) {
            $saved = (int) $row['saved'];
            $target = (int) $row['objetivo_centavos'];

            if ($target > 0 && $saved >= $target) {
                $completeUsers[(int) $row['user_id']] = true;
            }

            $monthly = (float) $row['monthly'];

            if ($monthly > 0) {
                $months[] = $saved / $monthly;
            }
        }

        $complete = count($completeUsers);

        return [
            'enough' => true,
            'users' => $users,
            'complete' => $complete,
            'percent' => round(($complete / max(1, $users)) * 100, 1),
            'average_months' => $months === [] ? null : round(array_sum($months) / count($months), 1),
        ];
    }

    /**
     * @return array<string, array{enough: bool, users: int, percent: ?float}>
     */
    private function featureUsage(int $activeUsers, int $min): array
    {
        $features = ['calendario', 'metas', 'deudas', 'deseos', 'estado_cuenta', 'inversion', 'donacion', 'logros', 'resumen'];
        $result = [];

        foreach ($features as $feature) {
            $statement = $this->db->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM analytics_eventos
                 WHERE evento = 'page_view'
                   AND JSON_UNQUOTE(JSON_EXTRACT(contexto_json, '$.feature')) = ?
                   AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)"
            );
            $statement->execute([$feature]);
            $users = (int) $statement->fetchColumn();

            $result[$feature] = [
                'enough' => $activeUsers >= $min,
                'users' => $users,
                'percent' => $activeUsers >= $min ? round(($users / max(1, $activeUsers)) * 100, 1) : null,
            ];
        }

        return $result;
    }

    /**
     * @return list<array{month: string, new_users: int, active_users: int, savers: int, enough: bool}>
     */
    private function evolution(int $min): array
    {
        $result = [];
        $now = new DateTimeImmutable('first day of this month', new DateTimeZone('UTC'));

        for ($i = 5; $i >= 0; $i--) {
            $start = $now->modify('-' . $i . ' months');
            $end = $start->modify('+1 month');
            $startSql = $start->format('Y-m-d H:i:s');
            $endSql = $end->format('Y-m-d H:i:s');

            $newUsers = $this->scalar(
                'SELECT COUNT(*) FROM users WHERE created_at >= ? AND created_at < ?',
                [$startSql, $endSql]
            );
            $active = $this->scalar(
                'SELECT COUNT(DISTINCT user_id) FROM analytics_eventos
                 WHERE user_id IS NOT NULL AND created_at >= ? AND created_at < ?',
                [$startSql, $endSql]
            );
            $savers = $this->scalar(
                'SELECT COUNT(DISTINCT user_id) FROM aportaciones_ahorro WHERE fecha >= ? AND fecha < ?',
                [$startSql, $endSql]
            );

            $result[] = [
                'month' => $start->format('Y-m'),
                'new_users' => $newUsers,
                'active_users' => $active,
                'savers' => $savers,
                'enough' => $active >= $min,
            ];
        }

        return $result;
    }

    /**
     * @return array{enough: bool, cohorts: list<array<string, mixed>>}
     */
    private function retention(int $min): array
    {
        $today = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $weekStart = $today->modify('-' . ((int) $today->format('N') - 1) . ' days')->setTime(0, 0);
        $cohorts = [];

        for ($offset = 7; $offset >= 0; $offset--) {
            $start = $weekStart->modify('-' . $offset . ' weeks');
            $end = $start->modify('+1 week');
            $size = $this->scalar(
                'SELECT COUNT(*) FROM users WHERE created_at >= ? AND created_at < ?',
                [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]
            );

            if ($size < $min) {
                continue;
            }

            $weeks = [];
            $maxWeek = min(7, (int) floor(($today->getTimestamp() - $start->getTimestamp()) / 604800));

            for ($week = 0; $week <= $maxWeek; $week++) {
                $from = $start->modify('+' . $week . ' weeks');
                $to = $from->modify('+1 week');
                $active = $this->scalar(
                    'SELECT COUNT(DISTINCT user_id) FROM analytics_eventos
                     WHERE created_at >= ? AND created_at < ?
                       AND user_id IN (SELECT id FROM users WHERE created_at >= ? AND created_at < ?)',
                    [
                        $from->format('Y-m-d H:i:s'),
                        $to->format('Y-m-d H:i:s'),
                        $start->format('Y-m-d H:i:s'),
                        $end->format('Y-m-d H:i:s'),
                    ]
                );
                $weeks[$week] = $size > 0 ? round(($active / $size) * 100, 1) : 0.0;
            }

            $cohorts[] = [
                'label' => $start->format('Y-m-d'),
                'size' => $size,
                'weeks' => $weeks,
            ];
        }

        return ['enough' => $cohorts !== [], 'cohorts' => $cohorts];
    }

    /**
     * @param list<int|string> $params
     */
    private function scalar(string $query, array $params): int
    {
        $statement = $this->db->prepare($query);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }
}
