<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

final class TagRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, COUNT(gt.gasto_id) AS usos
             FROM tags t
             LEFT JOIN gasto_tags gt ON gt.tag_id = t.id
             WHERE t.user_id = ?
             GROUP BY t.id
             ORDER BY t.nombre_normalizado ASC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function findOrCreate(int $userId, string $normalized): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tags (user_id, nombre_normalizado, created_at, updated_at)
             VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), updated_at = NOW()'
        );
        $stmt->execute([$userId, $normalized]);

        return (int) $this->db->lastInsertId();
    }

    public function attach(int $gastoId, int $tagId): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO gasto_tags (gasto_id, tag_id) VALUES (?, ?)'
        );
        $stmt->execute([$gastoId, $tagId]);
    }

    public function detachAll(int $gastoId): void
    {
        $stmt = $this->db->prepare('DELETE FROM gasto_tags WHERE gasto_id = ?');
        $stmt->execute([$gastoId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forGasto(int $gastoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.* FROM tags t
             JOIN gasto_tags gt ON gt.tag_id = t.id
             WHERE gt.gasto_id = ?
             ORDER BY t.nombre_normalizado ASC'
        );
        $stmt->execute([$gastoId]);

        return $stmt->fetchAll();
    }

    /**
     * @param list<int> $gastoIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function forGastos(array $gastoIds): array
    {
        $gastoIds = array_values(array_filter(array_map('intval', $gastoIds)));

        if ($gastoIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($gastoIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT gt.gasto_id, t.id, t.nombre_normalizado
             FROM gasto_tags gt
             JOIN tags t ON t.id = gt.tag_id
             WHERE gt.gasto_id IN ({$placeholders})
             ORDER BY t.nombre_normalizado ASC"
        );
        $stmt->execute($gastoIds);

        $grouped = [];

        foreach ($stmt->fetchAll() as $row) {
            $grouped[(int) $row['gasto_id']][] = $row;
        }

        return $grouped;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tags WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function rename(int $id, int $userId, string $normalized): void
    {
        $stmt = $this->db->prepare('UPDATE tags SET nombre_normalizado = ?, updated_at = NOW() WHERE id = ? AND user_id = ?');
        $stmt->execute([$normalized, $id, $userId]);
    }

    public function merge(int $sourceId, int $targetId, int $userId): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO gasto_tags (gasto_id, tag_id)
             SELECT gasto_id, ? FROM gasto_tags WHERE tag_id = ?'
        );
        $stmt->execute([$targetId, $sourceId]);

        $deleteLinks = $this->db->prepare('DELETE FROM gasto_tags WHERE tag_id = ?');
        $deleteLinks->execute([$sourceId]);

        $deleteTag = $this->db->prepare('DELETE FROM tags WHERE id = ? AND user_id = ?');
        $deleteTag->execute([$sourceId, $userId]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM tags WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }
}
