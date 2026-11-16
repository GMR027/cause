<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

final class GastoRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @param array{
     *     user_id: int, periodo_inicio: string, periodo_fin: string,
     *     monto_centavos: int, monto_principal_centavos: ?int, moneda: string,
     *     monto_total_compartido_centavos: ?int, monto_total_compartido_principal_centavos: ?int,
     *     parte_metodo: string, subcategoria_id: ?int, subcategoria_nombre: ?string,
     *     fecha: string, nota: ?string, foto_path: ?string, medio_pago: ?string,
     *     estado: string, fuente_exceso: string, confirmado: int,
     *     es_recurrente: int, gasto_recurrente_id: ?int
     * } $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO gastos
                (user_id, periodo_inicio, periodo_fin, monto_centavos, monto_principal_centavos, moneda,
                 monto_total_compartido_centavos, monto_total_compartido_principal_centavos, parte_metodo, subcategoria_id, subcategoria_nombre,
                 fecha, nota, foto_path, medio_pago, estado, fuente_exceso, confirmado,
                 es_recurrente, gasto_recurrente_id, created_at, updated_at)
             VALUES
                (:user_id, :periodo_inicio, :periodo_fin, :monto_centavos, :monto_principal_centavos, :moneda,
                 :monto_total, :monto_total_principal, :parte_metodo, :subcategoria_id, :subcategoria_nombre,
                 :fecha, :nota, :foto_path, :medio_pago, :estado, :fuente_exceso, :confirmado,
                 :es_recurrente, :gasto_recurrente_id, NOW(), NOW())'
        );

        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':periodo_inicio' => $data['periodo_inicio'],
            ':periodo_fin' => $data['periodo_fin'],
            ':monto_centavos' => $data['monto_centavos'],
            ':monto_principal_centavos' => $data['monto_principal_centavos'] ?? null,
            ':moneda' => $data['moneda'],
            ':monto_total' => $data['monto_total_compartido_centavos'],
            ':monto_total_principal' => $data['monto_total_compartido_principal_centavos'] ?? null,
            ':parte_metodo' => $data['parte_metodo'],
            ':subcategoria_id' => $data['subcategoria_id'],
            ':subcategoria_nombre' => $data['subcategoria_nombre'],
            ':fecha' => $data['fecha'],
            ':nota' => $data['nota'],
            ':foto_path' => $data['foto_path'],
            ':medio_pago' => $data['medio_pago'],
            ':estado' => $data['estado'],
            ':fuente_exceso' => $data['fuente_exceso'],
            ':confirmado' => $data['confirmado'],
            ':es_recurrente' => $data['es_recurrente'],
            ':gasto_recurrente_id' => $data['gasto_recurrente_id'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM gastos WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, int $userId, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE gastos SET
                monto_centavos = :monto_centavos,
                monto_principal_centavos = :monto_principal_centavos,
                moneda = :moneda,
                monto_total_compartido_centavos = :monto_total,
                monto_total_compartido_principal_centavos = :monto_total_principal,
                parte_metodo = :parte_metodo,
                subcategoria_id = :subcategoria_id,
                subcategoria_nombre = :subcategoria_nombre,
                fecha = :fecha,
                nota = :nota,
                foto_path = :foto_path,
                medio_pago = :medio_pago,
                estado = :estado,
                fuente_exceso = :fuente_exceso,
                updated_at = NOW()
             WHERE id = :id AND user_id = :user_id'
        );

        $stmt->execute([
            ':monto_centavos' => $data['monto_centavos'],
            ':monto_principal_centavos' => $data['monto_principal_centavos'] ?? null,
            ':moneda' => $data['moneda'],
            ':monto_total' => $data['monto_total_compartido_centavos'],
            ':monto_total_principal' => $data['monto_total_compartido_principal_centavos'] ?? null,
            ':parte_metodo' => $data['parte_metodo'],
            ':subcategoria_id' => $data['subcategoria_id'],
            ':subcategoria_nombre' => $data['subcategoria_nombre'],
            ':fecha' => $data['fecha'],
            ':nota' => $data['nota'],
            ':foto_path' => $data['foto_path'],
            ':medio_pago' => $data['medio_pago'],
            ':estado' => $data['estado'],
            ':fuente_exceso' => $data['fuente_exceso'],
            ':id' => $id,
            ':user_id' => $userId,
        ]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM gastos WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }

    public function confirm(int $id, int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE gastos SET confirmado = 1, updated_at = NOW() WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
    }

    public function sumAssignedForPart(int $userId, string $part, string $periodoInicio, string $periodoFin): int
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(a.monto_principal_centavos), 0)
             FROM asignaciones a
             JOIN ingresos i ON i.id = a.ingreso_id
             WHERE a.user_id = ?
               AND a.categoria = ?
               AND i.periodo_inicio = ?
               AND i.periodo_fin = ?
               AND a.estado IN ('acreditado', 'justificado')"
        );
        $stmt->execute([$userId, $part, $periodoInicio, $periodoFin]);
        $original = (int) $stmt->fetchColumn();

        $net = (new MoveRepository())->netForCategory($userId, $part, $periodoInicio, $periodoFin);

        return $original + $net;
    }

    public function sumOriginalAssignedForPart(
        int $userId,
        string $part,
        string $periodoInicio,
        string $periodoFin
    ): int {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(a.monto_principal_centavos), 0)
             FROM asignaciones a
             JOIN ingresos i ON i.id = a.ingreso_id
             WHERE a.user_id = ?
               AND a.categoria = ?
               AND i.periodo_inicio = ?
               AND i.periodo_fin = ?
               AND a.estado IN ('acreditado', 'justificado')"
        );
        $stmt->execute([$userId, $part, $periodoInicio, $periodoFin]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param list<string> $categories
     */
    public function sumAssignedForCategories(
        int $userId,
        array $categories,
        string $periodoInicio,
        string $periodoFin
    ): int {
        $categories = array_values(array_filter($categories, 'is_string'));

        if ($categories === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($categories), '?'));
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(a.monto_principal_centavos), 0)
             FROM asignaciones a
             JOIN ingresos i ON i.id = a.ingreso_id
             WHERE a.user_id = ?
               AND a.categoria IN ({$placeholders})
               AND i.periodo_inicio = ?
               AND i.periodo_fin = ?
               AND a.estado IN ('acreditado', 'justificado')"
        );
        $stmt->execute(array_merge([$userId], $categories, [$periodoInicio, $periodoFin]));
        $original = (int) $stmt->fetchColumn();

        $netStmt = $this->db->prepare(
            "SELECT COALESCE(SUM(
                CASE WHEN destino IN ({$placeholders}) THEN monto_centavos
                     WHEN origen IN ({$placeholders}) THEN -monto_centavos
                     ELSE 0 END
             ), 0)
             FROM movimientos
             WHERE user_id = ? AND periodo_inicio = ? AND periodo_fin = ?"
        );
        $netStmt->execute(array_merge($categories, $categories, [$userId, $periodoInicio, $periodoFin]));

        return $original + (int) $netStmt->fetchColumn();
    }

    public function sumSpentForPart(int $userId, string $part, string $periodoInicio, string $periodoFin): int
    {
        return $this->sumSpentForPartExcluding(0, $userId, $part, $periodoInicio, $periodoFin);
    }

    public function sumSpentForPartExcluding(
        int $excludeGastoId,
        int $userId,
        string $part,
        string $periodoInicio,
        string $periodoFin
    ): int {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(monto_principal_centavos), 0)
             FROM gastos
             WHERE user_id = ?
               AND parte_metodo = ?
               AND periodo_inicio = ?
               AND periodo_fin = ?
               AND estado = 'asignado'
               AND id <> ?"
        );
        $stmt->execute([$userId, $part, $periodoInicio, $periodoFin, $excludeGastoId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM gastos
             WHERE user_id = ? AND estado = 'pendiente_asignacion'
             ORDER BY fecha ASC, id ASC"
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function pendingCount(int $userId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM gastos WHERE user_id = ? AND estado = 'pendiente_asignacion'"
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $userId, array $filters = [], int $limit = 200): array
    {
        $where = ['g.user_id = ?'];
        $params = [$userId];

        if (!empty($filters['periodo_inicio']) && !empty($filters['periodo_fin'])) {
            $where[] = 'g.periodo_inicio = ? AND g.periodo_fin = ?';
            $params[] = $filters['periodo_inicio'];
            $params[] = $filters['periodo_fin'];
        }

        if (!empty($filters['parte_metodo'])) {
            $where[] = 'g.parte_metodo = ?';
            $params[] = $filters['parte_metodo'];
        }

        if (isset($filters['subcategoria_id']) && $filters['subcategoria_id'] !== '') {
            if ((int) $filters['subcategoria_id'] === 0) {
                $where[] = 'g.subcategoria_id IS NULL';
            } else {
                $where[] = 'g.subcategoria_id = ?';
                $params[] = (int) $filters['subcategoria_id'];
            }
        }

        if (!empty($filters['medio_pago'])) {
            $where[] = 'g.medio_pago = ?';
            $params[] = $filters['medio_pago'];
        }

        if (!empty($filters['tag_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM gasto_tags gt WHERE gt.gasto_id = g.id AND gt.tag_id = ?)';
            $params[] = (int) $filters['tag_id'];
        }

        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            if (mb_strlen($q) >= 3) {
                $where[] = '(MATCH(g.nota, g.subcategoria_nombre) AGAINST (? IN NATURAL LANGUAGE MODE)
                    OR g.nota LIKE ? OR g.subcategoria_nombre LIKE ?)';
                $params[] = $q;
                $params[] = '%' . $q . '%';
                $params[] = '%' . $q . '%';
            } else {
                $where[] = '(g.nota LIKE ? OR g.subcategoria_nombre LIKE ?)';
                $params[] = '%' . $q . '%';
                $params[] = '%' . $q . '%';
            }
        }

        $limit = max(1, min(500, $limit));
        $sql = 'SELECT g.* FROM gastos g WHERE ' . implode(' AND ', $where)
            . " ORDER BY g.fecha DESC, g.id DESC LIMIT {$limit}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @return list<array{periodo_inicio: string, periodo_fin: string, total: int}>
     */
    public function distinctPeriods(int $userId, int $limit = 24): array
    {
        $limit = max(1, min(60, $limit));
        $stmt = $this->db->query(
            "SELECT periodo_inicio, periodo_fin, COUNT(*) AS total
             FROM gastos
             WHERE user_id = {$userId}
             GROUP BY periodo_inicio, periodo_fin
             ORDER BY periodo_inicio DESC
             LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $userId, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->db->query(
            "SELECT * FROM gastos WHERE user_id = {$userId}
             ORDER BY fecha DESC, id DESC LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }

    public function lastPaymentMethod(int $userId): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT medio_pago, COUNT(*) AS usos, MAX(fecha) AS ultima
             FROM gastos
             WHERE user_id = ? AND medio_pago IS NOT NULL
             GROUP BY medio_pago
             ORDER BY usos DESC, ultima DESC
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $value = $stmt->fetchColumn(0);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function unconfirmedRecurring(int $userId, string $periodoInicio, string $periodoFin): array
    {
        $stmt = $this->db->prepare(
            "SELECT g.*, r.nombre AS recurrente_nombre
             FROM gastos g
             LEFT JOIN gastos_recurrentes r ON r.id = g.gasto_recurrente_id
             WHERE g.user_id = ?
               AND g.es_recurrente = 1
               AND g.confirmado = 0
               AND g.periodo_inicio = ?
               AND g.periodo_fin = ?
             ORDER BY g.fecha DESC, g.id DESC"
        );
        $stmt->execute([$userId, $periodoInicio, $periodoFin]);

        return $stmt->fetchAll();
    }

    public function sumByPeriod(int $userId, string $periodoInicio, string $periodoFin): int
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(monto_principal_centavos), 0)
             FROM gastos
             WHERE user_id = ? AND periodo_inicio = ? AND periodo_fin = ?
               AND estado <> 'cerrado'"
        );
        $stmt->execute([$userId, $periodoInicio, $periodoFin]);

        return (int) $stmt->fetchColumn();
    }

    public function sumBetween(int $userId, string $inicio, string $fin): int
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(monto_principal_centavos), 0)
             FROM gastos
             WHERE user_id = ? AND fecha BETWEEN ? AND ? AND estado <> 'cerrado'"
        );
        $stmt->execute([$userId, $inicio, $fin]);

        return (int) $stmt->fetchColumn();
    }

    public function sumByPartPeriod(int $userId, string $part, string $periodoInicio, string $periodoFin): int
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(monto_principal_centavos), 0)
             FROM gastos
             WHERE user_id = ? AND parte_metodo = ?
               AND periodo_inicio = ? AND periodo_fin = ?
               AND estado = 'asignado'"
        );
        $stmt->execute([$userId, $part, $periodoInicio, $periodoFin]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array{subcategoria_id: int, subcategoria_nombre: string, usos: int}>
     */
    public function subcategorySuggestions(int $userId, string $part, int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $stmt = $this->db->prepare(
            "SELECT subcategoria_id, subcategoria_nombre, COUNT(*) AS usos
             FROM gastos
             WHERE user_id = ? AND parte_metodo = ?
               AND subcategoria_id IS NOT NULL
               AND subcategoria_nombre IS NOT NULL
             GROUP BY subcategoria_id, subcategoria_nombre
             ORDER BY usos DESC, MAX(fecha) DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$userId, $part]);

        return $stmt->fetchAll();
    }

    /**
     * Uso histórico por parte y subcategoría: "parte|id" => usos.
     *
     * @return array<string, int>
     */
    public function subcategoryUsage(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT parte_metodo, subcategoria_id, COUNT(*) AS usos
             FROM gastos
             WHERE user_id = ? AND subcategoria_id IS NOT NULL
             GROUP BY parte_metodo, subcategoria_id'
        );
        $stmt->execute([$userId]);

        $usage = [];

        foreach ($stmt->fetchAll() as $row) {
            $usage[$row['parte_metodo'] . '|' . (int) $row['subcategoria_id']] = (int) $row['usos'];
        }

        return $usage;
    }

    /**
     * Gastos más frecuentes desde una fecha (UTC): agrupa por subcategoría o,
     * si no tiene, por la nota normalizada. Excluye recurrentes y sin confirmar.
     *
     * @return list<array<string, mixed>>
     */
    public function topFrequent(int $userId, string $desdeUtc, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->db->prepare(
            "SELECT
                CASE WHEN subcategoria_id IS NOT NULL THEN CONCAT('s', subcategoria_id)
                     ELSE CONCAT('n', LOWER(TRIM(LEFT(nota, 120)))) END AS clave,
                MAX(subcategoria_nombre) AS subcategoria_nombre,
                SUBSTRING_INDEX(GROUP_CONCAT(TRIM(LEFT(nota, 120)) ORDER BY fecha DESC SEPARATOR '\\n'), '\\n', 1) AS nota,
                SUBSTRING_INDEX(GROUP_CONCAT(parte_metodo ORDER BY fecha DESC SEPARATOR '\\n'), '\\n', 1) AS parte_metodo,
                COUNT(*) AS veces,
                COALESCE(SUM(COALESCE(monto_principal_centavos, monto_centavos)), 0) AS total,
                MAX(fecha) AS ultima
             FROM gastos
             WHERE user_id = ?
               AND fecha >= ?
               AND confirmado = 1
               AND es_recurrente = 0
               AND estado <> 'cerrado'
               AND (subcategoria_id IS NOT NULL OR (nota IS NOT NULL AND TRIM(nota) <> ''))
             GROUP BY clave
             ORDER BY veces DESC, total DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$userId, $desdeUtc]);

        return $stmt->fetchAll();
    }
}
