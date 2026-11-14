<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

final class SummaryRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $userId, string $inicio, string $fin): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM resumenes_mensuales
             WHERE user_id = ? AND periodo_inicio = ? AND periodo_fin = ?
             LIMIT 1'
        );
        $stmt->execute([$userId, $inicio, $fin]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM resumenes_mensuales WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $content
     */
    public function create(int $userId, string $inicio, string $fin, array $content): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO resumenes_mensuales
                (user_id, periodo_inicio, periodo_fin, contenido_json, generado_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())'
        );
        $stmt->execute([
            $userId,
            $inicio,
            $fin,
            json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $userId, int $limit = 24): array
    {
        $limit = max(1, min(60, $limit));
        $stmt = $this->db->query(
            "SELECT * FROM resumenes_mensuales WHERE user_id = {$userId}
             ORDER BY periodo_inicio DESC, id DESC LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }

    public function updateReflection(int $id, int $userId, ?string $text): void
    {
        $stmt = $this->db->prepare(
            'UPDATE resumenes_mensuales SET reflexion = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$text, $id, $userId]);
    }

    public function markRead(int $id, int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE resumenes_mensuales SET leido_at = NOW(), updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
    }

    public function count(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM resumenes_mensuales WHERE user_id = ?');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }
}
