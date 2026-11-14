<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

final class NoControladoRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function create(
        int $userId,
        string $periodoInicio,
        string $periodoFin,
        int $montoCentavos,
        string $moneda,
        string $origen,
        ?int $referenciaId = null,
        ?string $nota = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO no_controlado
                (user_id, periodo_inicio, periodo_fin, monto_centavos, monto_principal_centavos, moneda,
                 origen, referencia_id, nota, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );

        $stmt->execute([
            $userId,
            $periodoInicio,
            $periodoFin,
            $montoCentavos,
            $montoCentavos,
            $moneda,
            $origen,
            $referenciaId,
            $nota,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function totalForPeriod(int $userId, string $periodoInicio, string $periodoFin): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(monto_principal_centavos), 0)
             FROM no_controlado
             WHERE user_id = ? AND periodo_inicio = ? AND periodo_fin = ?'
        );
        $stmt->execute([$userId, $periodoInicio, $periodoFin]);

        return (int) $stmt->fetchColumn();
    }

    public function deleteByReferencia(int $userId, string $origen, int $referenciaId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM no_controlado
             WHERE user_id = ? AND origen = ? AND referencia_id = ?'
        );
        $stmt->execute([$userId, $origen, $referenciaId]);
    }

    public function sumByPeriod(int $userId, string $periodoInicio, string $periodoFin): int
    {
        return $this->totalForPeriod($userId, $periodoInicio, $periodoFin);
    }
}
