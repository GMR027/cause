<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

final class PauseRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForPeriod(int $userId, string $inicio, string $fin): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM pausas_inversion
             WHERE user_id = ? AND periodo_inicio = ? AND periodo_fin = ?
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId, $inicio, $fin]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function create(
        int $userId,
        string $inicio,
        string $fin,
        int $amount,
        string $reason,
        ?string $text
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO pausas_inversion
                (user_id, periodo_inicio, periodo_fin, monto_centavos, motivo_catalogo, motivo_texto, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$userId, $inicio, $fin, $amount, $reason, $text]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $userId, int $amount, string $reason, ?string $text): void
    {
        $stmt = $this->db->prepare(
            'UPDATE pausas_inversion SET monto_centavos = ?, motivo_catalogo = ?, motivo_texto = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$amount, $reason, $text, $id, $userId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $userId, int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->db->query(
            "SELECT * FROM pausas_inversion WHERE user_id = {$userId}
             ORDER BY periodo_inicio DESC, id DESC LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }
}
