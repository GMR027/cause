<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

final class AsignacionRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @param list<array{categoria: string, monto_centavos: int}> $rows
     */
    public function createMany(int $ingresoId, int $userId, array $rows, string $moneda): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO asignaciones
                (ingreso_id, user_id, categoria, monto_centavos, monto_principal_centavos, moneda, estado, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 'pendiente_asignacion', NOW(), NOW())"
        );

        foreach ($rows as $row) {
            $stmt->execute([
                $ingresoId,
                $userId,
                $row['categoria'],
                $row['monto_centavos'],
                $row['monto_centavos'],
                $moneda,
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forIngreso(int $ingresoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM asignaciones WHERE ingreso_id = ? ORDER BY id ASC'
        );
        $stmt->execute([$ingresoId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM asignaciones WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function updateEstado(int $id, string $estado, ?int $justificacionId = null): void
    {
        $stmt = $this->db->prepare(
            'UPDATE asignaciones
             SET estado = ?, justificacion_id = ?, updated_at = NOW()
             WHERE id = ? AND estado = ?'
        );

        $stmt->execute([$estado, $justificacionId, $id, 'pendiente_asignacion']);
    }

    public function pendingCountForIngreso(int $ingresoId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM asignaciones
             WHERE ingreso_id = ? AND estado = 'pendiente_asignacion'"
        );
        $stmt->execute([$ingresoId]);

        return (int) $stmt->fetchColumn();
    }
}
