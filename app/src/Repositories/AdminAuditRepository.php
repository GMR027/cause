<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;
use Throwable;

final class AdminAuditRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @param array<string, mixed> $details
     */
    public function log(
        int $adminId,
        string $action,
        ?string $entity = null,
        ?int $entityId = null,
        array $details = []
    ): void {
        $actor = $this->db->prepare('SELECT alias, email FROM users WHERE id = ? LIMIT 1');
        $actor->execute([$adminId]);
        $actorRow = $actor->fetch();

        $stmt = $this->db->prepare(
            'INSERT INTO admin_audit_log
                (admin_id, admin_alias, admin_email, accion, entidad, entidad_id,
                 detalles_json, ip, user_agent, created_at, updated_at)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );

        $stmt->execute([
            $adminId,
            is_array($actorRow) ? (string) $actorRow['alias'] : null,
            is_array($actorRow) ? (string) $actorRow['email'] : null,
            $action,
            $entity,
            $entityId,
            $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $_SERVER['REMOTE_ADDR'] ?? null,
            isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        $stmt = $this->db->query(
            "SELECT a.*, COALESCE(a.admin_alias, u.alias) AS admin_alias,
                    COALESCE(a.admin_email, u.email) AS admin_email
             FROM admin_audit_log a
             LEFT JOIN users u ON u.id = a.admin_id
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM admin_audit_log')->fetchColumn();
    }
}
