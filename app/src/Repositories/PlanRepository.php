<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

/**
 * Historial de planes de gastos y sus conceptos planeados.
 */
final class PlanRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @param array{
     *     user_id: int, fecha_ingreso: string, monto_centavos: int, moneda: string,
     *     monto_principal_centavos: int, metodo_id: ?int, metodo_codigo: ?string,
     *     metodo_nombre: ?string, porcentajes: array<string, float>,
     *     resumen: array<string, mixed>, notas: ?string
     * } $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO planes_gastos
                (user_id, fecha_ingreso, monto_centavos, moneda, monto_principal_centavos,
                 metodo_id, metodo_codigo, metodo_nombre, porcentajes_json, resumen_json,
                 notas, estado, created_at, updated_at)
             VALUES
                (:user_id, :fecha_ingreso, :monto_centavos, :moneda, :monto_principal_centavos,
                 :metodo_id, :metodo_codigo, :metodo_nombre, :porcentajes_json, :resumen_json,
                 :notas, \'activo\', NOW(), NOW())'
        );

        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':fecha_ingreso' => $data['fecha_ingreso'],
            ':monto_centavos' => $data['monto_centavos'],
            ':moneda' => $data['moneda'],
            ':monto_principal_centavos' => $data['monto_principal_centavos'],
            ':metodo_id' => $data['metodo_id'],
            ':metodo_codigo' => $data['metodo_codigo'],
            ':metodo_nombre' => $data['metodo_nombre'],
            ':porcentajes_json' => json_encode($data['porcentajes'], JSON_UNESCAPED_UNICODE),
            ':resumen_json' => json_encode($data['resumen'], JSON_UNESCAPED_UNICODE),
            ':notas' => $data['notas'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array{
     *     fecha_ingreso: string, monto_centavos: int, moneda: string,
     *     monto_principal_centavos: int, metodo_id: ?int, metodo_codigo: ?string,
     *     metodo_nombre: ?string, porcentajes: array<string, float>,
     *     resumen: array<string, mixed>, notas: ?string
     * } $data
     */
    public function update(int $id, int $userId, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE planes_gastos SET
                fecha_ingreso = :fecha_ingreso,
                monto_centavos = :monto_centavos,
                moneda = :moneda,
                monto_principal_centavos = :monto_principal_centavos,
                metodo_id = :metodo_id,
                metodo_codigo = :metodo_codigo,
                metodo_nombre = :metodo_nombre,
                porcentajes_json = :porcentajes_json,
                resumen_json = :resumen_json,
                notas = :notas,
                updated_at = NOW()
             WHERE id = :id AND user_id = :user_id'
        );

        $stmt->execute([
            ':fecha_ingreso' => $data['fecha_ingreso'],
            ':monto_centavos' => $data['monto_centavos'],
            ':moneda' => $data['moneda'],
            ':monto_principal_centavos' => $data['monto_principal_centavos'],
            ':metodo_id' => $data['metodo_id'],
            ':metodo_codigo' => $data['metodo_codigo'],
            ':metodo_nombre' => $data['metodo_nombre'],
            ':porcentajes_json' => json_encode($data['porcentajes'], JSON_UNESCAPED_UNICODE),
            ':resumen_json' => json_encode($data['resumen'], JSON_UNESCAPED_UNICODE),
            ':notas' => $data['notas'],
            ':id' => $id,
            ':user_id' => $userId,
        ]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM planes_gastos WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM planes_gastos WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array{estado?: string, momento?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function allForUser(int $userId, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        [$where, $params] = $this->whereForUser($userId, $filters);

        $stmt = $this->db->prepare(
            'SELECT * FROM planes_gastos WHERE ' . $where . '
             ORDER BY fecha_ingreso DESC, created_at DESC, id DESC
             LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @param array{estado?: string, momento?: string} $filters
     */
    public function countForUser(int $userId, array $filters = []): int
    {
        [$where, $params] = $this->whereForUser($userId, $filters);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM planes_gastos WHERE ' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Plan activo más cercano a la fecha de hoy.
     *
     * @return array<string, mixed>|null
     */
    public function nextForUser(int $userId, string $today): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM planes_gastos
             WHERE user_id = ? AND estado = 'activo'
             ORDER BY
                CASE WHEN fecha_ingreso >= ? THEN 0 ELSE 1 END,
                CASE WHEN fecha_ingreso >= ? THEN fecha_ingreso END ASC,
                fecha_ingreso DESC,
                created_at DESC
             LIMIT 1"
        );
        $stmt->execute([$userId, $today, $today]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function existsForDate(int $userId, string $date, int $excludeId = 0): bool
    {
        $sql = 'SELECT COUNT(*) FROM planes_gastos WHERE user_id = ? AND fecha_ingreso = ?';
        $params = [$userId, $date];

        if ($excludeId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function markCompleted(int $id, int $userId, ?int $ingresoId = null): void
    {
        $stmt = $this->db->prepare(
            "UPDATE planes_gastos
             SET estado = 'completado', ingreso_id = COALESCE(?, ingreso_id), updated_at = NOW()
             WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$ingresoId, $id, $userId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function itemsForPlan(int $planId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM plan_gastos_items WHERE plan_id = ? ORDER BY seccion ASC, orden ASC, id ASC'
        );
        $stmt->execute([$planId]);

        return $stmt->fetchAll();
    }

    /**
     * @param list<array{seccion: string, concepto: string, monto_centavos: int}> $items
     */
    public function replaceItems(int $planId, array $items): void
    {
        $delete = $this->db->prepare('DELETE FROM plan_gastos_items WHERE plan_id = ?');
        $delete->execute([$planId]);

        if ($items === []) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO plan_gastos_items
                (plan_id, seccion, concepto, monto_centavos, orden, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
        );

        $order = 0;

        foreach ($items as $item) {
            $stmt->execute([
                $planId,
                $item['seccion'],
                $item['concepto'],
                $item['monto_centavos'],
                $order++,
            ]);
        }
    }

    /**
     * @param array{estado?: string, momento?: string} $filters
     * @return array{0: string, 1: list<mixed>}
     */
    private function whereForUser(int $userId, array $filters): array
    {
        $where = ['user_id = ?'];
        $params = [$userId];

        $estado = (string) ($filters['estado'] ?? '');

        if (in_array($estado, ['activo', 'completado'], true)) {
            $where[] = 'estado = ?';
            $params[] = $estado;
        }

        $momento = (string) ($filters['momento'] ?? '');

        if ($momento === 'proximos') {
            $where[] = 'fecha_ingreso >= CURDATE()';
        } elseif ($momento === 'pasados') {
            $where[] = 'fecha_ingreso < CURDATE()';
        }

        return [implode(' AND ', $where), $params];
    }
}
