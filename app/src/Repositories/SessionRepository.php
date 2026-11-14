<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

final class SessionRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function create(string $id, int $userId, ?string $ip, ?string $userAgent): void
    {
        $stmt = $this->db->prepare(
            'REPLACE INTO sesiones (id, user_id, ip, user_agent, last_activity, created_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())'
        );

        $stmt->execute([$id, $userId, $ip, $userAgent]);
    }

    public function touch(string $id): void
    {
        $stmt = $this->db->prepare('UPDATE sesiones SET last_activity = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function find(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sesiones WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $session = $stmt->fetch();

        return is_array($session) ? $session : null;
    }

    public function delete(string $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM sesiones WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function deleteAllForUser(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM sesiones WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    public function countForUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM sesiones WHERE user_id = ?');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ip, user_agent, last_activity, created_at
             FROM sesiones WHERE user_id = ?
             ORDER BY last_activity DESC, created_at DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }
}
