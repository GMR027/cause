<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

/**
 * Persistencia de racha, nivel, retos, hitos y preferencias de progreso.
 */
final class GamificationRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function streak(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM rachas WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function ensureStreak(int $userId): array
    {
        $row = $this->streak($userId);

        if ($row !== null) {
            return $row;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO rachas
                (user_id, periodos_consecutivos, mejor_racha, estado, created_at, updated_at)
             VALUES (?, 0, 0, "activa", NOW(), NOW())'
        );
        $stmt->execute([$userId]);

        return $this->streak($userId) ?? [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveStreak(int $userId, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE rachas SET
                periodos_consecutivos = ?, mejor_racha = ?, estado = ?,
                ultimo_periodo_inicio = ?, ultimo_periodo_fin = ?, congelada_desde = ?,
                updated_at = NOW()
             WHERE user_id = ?'
        );
        $stmt->execute([
            (int) ($data['periodos_consecutivos'] ?? 0),
            (int) ($data['mejor_racha'] ?? 0),
            (string) ($data['estado'] ?? 'activa'),
            $data['ultimo_periodo_inicio'] ?? null,
            $data['ultimo_periodo_fin'] ?? null,
            $data['congelada_desde'] ?? null,
            $userId,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function level(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM niveles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $detail
     */
    public function saveLevel(int $userId, int $level, float $percent, string $date, array $detail): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO niveles
                (user_id, nivel_actual, porcentaje_cumplimiento, detalle_json, fecha_calculo, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                nivel_actual = VALUES(nivel_actual),
                porcentaje_cumplimiento = VALUES(porcentaje_cumplimiento),
                detalle_json = VALUES(detalle_json),
                fecha_calculo = VALUES(fecha_calculo),
                updated_at = NOW()'
        );
        $stmt->execute([
            $userId,
            $level,
            $percent,
            json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $date,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function challengeForPeriod(int $userId, string $start, string $end): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM retos
             WHERE user_id = ? AND periodo_inicio = ? AND periodo_fin = ?
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId, $start, $end]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findChallenge(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM retos WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function createChallenge(
        int $userId,
        string $start,
        string $end,
        string $type,
        ?float $objective
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO retos
                (user_id, periodo_inicio, periodo_fin, tipo, objetivo, progreso, estado, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 0, "activo", NOW(), NOW())'
        );
        $stmt->execute([$userId, $start, $end, $type, $objective]);

        return (int) $this->db->lastInsertId();
    }

    public function updateChallenge(int $id, float $progress, string $state): void
    {
        $stmt = $this->db->prepare(
            'UPDATE retos SET progreso = ?, estado = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$progress, $state, $id]);
    }

    public function dismissChallenge(int $id, int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE retos SET estado = "descartado", updated_at = NOW()
             WHERE id = ? AND user_id = ? AND estado IN ("activo", "completado")'
        );
        $stmt->execute([$id, $userId]);
    }

    public function expireChallenges(int $userId, string $beforeStart): void
    {
        $stmt = $this->db->prepare(
            'UPDATE retos SET estado = "expirado", updated_at = NOW()
             WHERE user_id = ? AND estado = "activo" AND periodo_inicio < ?'
        );
        $stmt->execute([$userId, $beforeStart]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function hitos(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM hitos_usuario WHERE user_id = ? ORDER BY fecha_logro ASC, id ASC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function hitosMap(int $userId): array
    {
        $map = [];

        foreach ($this->hitos($userId) as $hito) {
            $map[(string) $hito['tipo']] = $hito;
        }

        return $map;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function uncelebratedHito(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM hitos_usuario
             WHERE user_id = ? AND celebrado_at IS NULL
             ORDER BY fecha_logro ASC, id ASC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function markCelebrated(int $id, int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE hitos_usuario SET celebrado_at = NOW(), updated_at = NOW()
             WHERE id = ? AND user_id = ? AND celebrado_at IS NULL'
        );
        $stmt->execute([$id, $userId]);
    }

    public function markAllCelebrated(int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE hitos_usuario SET celebrado_at = NOW(), updated_at = NOW()
             WHERE user_id = ? AND celebrado_at IS NULL'
        );
        $stmt->execute([$userId]);
    }

    public function setGamification(int $userId, bool $active): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET gamificacion_activa = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$active ? 1 : 0, $userId]);
    }

    public function setSocialComparison(int $userId, bool $active): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET comparacion_social = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$active ? 1 : 0, $userId]);
    }

    /**
     * Períodos con al menos un ingreso finalizado (sin asignaciones pendientes).
     *
     * @return list<array{periodo_inicio: string, periodo_fin: string}>
     */
    public function finalizedPeriods(int $userId, int $limit = 120): array
    {
        $limit = max(1, min(240, $limit));
        $stmt = $this->db->prepare(
            "SELECT i.periodo_inicio, i.periodo_fin
             FROM ingresos i
             WHERE i.user_id = ?
               AND NOT EXISTS (
                   SELECT 1 FROM asignaciones a
                   WHERE a.ingreso_id = i.id AND a.estado = 'pendiente_asignacion'
               )
             GROUP BY i.periodo_inicio, i.periodo_fin
             ORDER BY i.periodo_inicio DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function hasFinalizedIncome(int $userId, string $start, string $end): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM ingresos i
             WHERE i.user_id = ? AND i.periodo_inicio = ? AND i.periodo_fin = ?
               AND NOT EXISTS (
                   SELECT 1 FROM asignaciones a
                   WHERE a.ingreso_id = i.id AND a.estado = 'pendiente_asignacion'
               )"
        );
        $stmt->execute([$userId, $start, $end]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Usuarios del mismo método, recurrencia y moneda con nivel calculado.
     *
     * @return list<array{id: int, porcentaje_cumplimiento: float}>
     */
    public function socialGroup(string $methodCode, string $recurrence, string $currency, int $excludeUserId): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, n.porcentaje_cumplimiento
             FROM users u
             JOIN usuario_metodos um ON um.user_id = u.id AND um.vigente_hasta IS NULL
             JOIN metodos m ON m.id = um.metodo_id
             JOIN niveles n ON n.user_id = u.id
             WHERE m.codigo = ?
               AND u.recurrencia = ?
               AND u.moneda_activa = ?
               AND n.nivel_actual > 0
               AND u.id <> ?'
        );
        $stmt->execute([$methodCode, $recurrence, $currency, $excludeUserId]);

        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'porcentaje_cumplimiento' => (float) $row['porcentaje_cumplimiento'],
            ];
        }

        return $rows;
    }

    /**
     * @return array{total: int, pending: int}
     */
    public function gastoCounts(int $userId, string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN estado = 'pendiente_asignacion' THEN 1 ELSE 0 END), 0) AS pending
             FROM gastos
             WHERE user_id = ? AND periodo_inicio = ? AND periodo_fin = ? AND estado <> 'cerrado'"
        );
        $stmt->execute([$userId, $start, $end]);
        $row = $stmt->fetch();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
        ];
    }
}
