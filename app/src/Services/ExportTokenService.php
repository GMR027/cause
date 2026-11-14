<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use PDO;

/**
 * Enlaces de un solo uso para exportaciones solicitadas por un admin (ARCO).
 */
final class ExportTokenService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function create(int $userId, ?int $requestedBy = null): string
    {
        $token = bin2hex(random_bytes(32));
        $hours = max(1, (int) config('security.export.token_hours', 72));

        $this->db->prepare(
            'DELETE FROM export_tokens WHERE user_id = ? AND used_at IS NULL AND expires_at < UTC_TIMESTAMP()'
        )->execute([$userId]);

        $statement = $this->db->prepare(
            'INSERT INTO export_tokens
                (user_id, requested_by, token_hash, expires_at, created_at, updated_at)
             VALUES (?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? HOUR), UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $statement->execute([$userId, $requestedBy, hash('sha256', $token), $hours]);

        return $token;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(string $token): ?array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM export_tokens
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()
             LIMIT 1'
        );
        $statement->execute([hash('sha256', $token)]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function consume(int $id): void
    {
        $this->db->prepare('UPDATE export_tokens SET used_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?')
            ->execute([$id]);
    }
}
