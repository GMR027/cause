<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

final class MoveRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function create(
        int $userId,
        string $inicio,
        string $fin,
        string $origin,
        string $destination,
        int $amount,
        string $currency,
        string $reason,
        ?string $text,
        string $date
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO movimientos
                (user_id, periodo_inicio, periodo_fin, origen, destino, monto_centavos,
                 monto_principal_centavos, moneda, motivo_catalogo, motivo_texto, fecha, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $userId,
            $inicio,
            $fin,
            $origin,
            $destination,
            $amount,
            $amount,
            $currency,
            $reason,
            $text,
            $date,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function netForCategory(
        int $userId,
        string $category,
        string $inicio,
        string $fin,
        int $excludeId = 0
    ): int {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(
                CASE WHEN destino = ? THEN monto_principal_centavos
                     WHEN origen = ? THEN -monto_principal_centavos
                     ELSE 0 END
             ), 0)
             FROM movimientos
             WHERE user_id = ? AND periodo_inicio = ? AND periodo_fin = ? AND id <> ?'
        );
        $stmt->execute([$category, $category, $userId, $inicio, $fin, $excludeId]);

        return (int) $stmt->fetchColumn();
    }

    public function sumIn(int $userId, string $category, string $inicio, string $fin): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(monto_principal_centavos), 0) FROM movimientos
             WHERE user_id = ? AND destino = ? AND periodo_inicio = ? AND periodo_fin = ?'
        );
        $stmt->execute([$userId, $category, $inicio, $fin]);

        return (int) $stmt->fetchColumn();
    }

    public function sumOut(int $userId, string $category, string $inicio, string $fin): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(monto_principal_centavos), 0) FROM movimientos
             WHERE user_id = ? AND origen = ? AND periodo_inicio = ? AND periodo_fin = ?'
        );
        $stmt->execute([$userId, $category, $inicio, $fin]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forPeriod(int $userId, string $inicio, string $fin): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM movimientos
             WHERE user_id = ? AND periodo_inicio = ? AND periodo_fin = ?
             ORDER BY fecha DESC, id DESC'
        );
        $stmt->execute([$userId, $inicio, $fin]);

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCategory(int $userId, string $category, int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->prepare(
            'SELECT * FROM movimientos
             WHERE user_id = ? AND (origen = ? OR destino = ?)
             ORDER BY fecha DESC, id DESC LIMIT ' . $limit
        );
        $stmt->execute([$userId, $category, $category]);

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $userId, int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->query(
            "SELECT * FROM movimientos WHERE user_id = {$userId}
             ORDER BY fecha DESC, id DESC LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }
}
