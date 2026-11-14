<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

final class IngresoRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @param array{
     *     user_id: int, metodo_id: ?int, periodo_inicio: string, periodo_fin: string,
     *     monto_centavos: int, monto_principal_centavos: ?int, moneda: string, tipo: string,
     *     recurrencia: ?string, fecha: string, nota: ?string
     * } $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ingresos
                (user_id, metodo_id, periodo_inicio, periodo_fin, monto_centavos, monto_principal_centavos, moneda,
                 tipo, recurrencia, fecha, nota, created_at, updated_at)
             VALUES
                (:user_id, :metodo_id, :periodo_inicio, :periodo_fin, :monto_centavos, :monto_principal_centavos, :moneda,
                 :tipo, :recurrencia, :fecha, :nota, NOW(), NOW())'
        );

        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':metodo_id' => $data['metodo_id'],
            ':periodo_inicio' => $data['periodo_inicio'],
            ':periodo_fin' => $data['periodo_fin'],
            ':monto_centavos' => $data['monto_centavos'],
            ':monto_principal_centavos' => $data['monto_principal_centavos'] ?? null,
            ':moneda' => $data['moneda'],
            ':tipo' => $data['tipo'],
            ':recurrencia' => $data['recurrencia'],
            ':fecha' => $data['fecha'],
            ':nota' => $data['nota'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ingresos WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Último ingreso con asignaciones pendientes.
     *
     * @return array<string, mixed>|null
     */
    public function pendingForUser(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT i.*, COUNT(a.id) AS pendientes
             FROM ingresos i
             JOIN asignaciones a ON a.ingreso_id = i.id AND a.estado = 'pendiente_asignacion'
             WHERE i.user_id = ?
             GROUP BY i.id
             ORDER BY i.id DESC
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function lastFixedAmount(int $userId): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(monto_principal_centavos, monto_centavos) FROM ingresos
             WHERE user_id = ? AND tipo = 'fijo'
             ORDER BY fecha DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (int) $value;
    }

    public function existsForPeriod(int $userId, string $periodoInicio, string $periodoFin): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM ingresos
             WHERE user_id = ? AND periodo_inicio = ? AND periodo_fin = ?
             LIMIT 1'
        );
        $stmt->execute([$userId, $periodoInicio, $periodoFin]);

        return $stmt->fetchColumn() !== false;
    }

    public function sumForPeriod(int $userId, string $periodoInicio, string $periodoFin): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(monto_principal_centavos), 0)
             FROM ingresos
             WHERE user_id = ? AND periodo_inicio = ? AND periodo_fin = ?'
        );
        $stmt->execute([$userId, $periodoInicio, $periodoFin]);

        return (int) $stmt->fetchColumn();
    }
}
