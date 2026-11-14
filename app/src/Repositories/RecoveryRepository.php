<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

final class RecoveryRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function questionsForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, pregunta, respuesta_hash, orden
             FROM preguntas_recuperacion
             WHERE user_id = ?
             ORDER BY orden ASC, id ASC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function createCode(int $userId, string $hash): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO codigos_recuperacion (user_id, codigo_hash, usado, created_at, updated_at)
             VALUES (?, ?, 0, NOW(), NOW())'
        );
        $stmt->execute([$userId, $hash]);
    }

    public function findCode(int $userId, string $hash): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM codigos_recuperacion
             WHERE user_id = ? AND codigo_hash = ? AND usado = 0
             LIMIT 1'
        );
        $stmt->execute([$userId, $hash]);
        $code = $stmt->fetch();

        return is_array($code) ? $code : null;
    }

    public function markCodeUsed(int $codeId): void
    {
        $stmt = $this->db->prepare('UPDATE codigos_recuperacion SET usado = 1 WHERE id = ?');
        $stmt->execute([$codeId]);
    }

    public function invalidateCodes(int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE codigos_recuperacion SET usado = 1 WHERE user_id = ? AND usado = 0'
        );
        $stmt->execute([$userId]);
    }

    public function logAttempt(int $userId, string $ip, bool $success): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO intentos_recuperacion (user_id, ip, exitoso, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$userId, $ip, $success ? 1 : 0]);
    }

    public function failedAttemptsSinceLastSuccess(int $userId, int $minutes): int
    {
        $minutes = max(1, $minutes);

        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM intentos_recuperacion
             WHERE user_id = :user_id
               AND exitoso = 0
               AND created_at > (NOW() - INTERVAL {$minutes} MINUTE)
               AND created_at > COALESCE(
                   (SELECT MAX(created_at)
                    FROM intentos_recuperacion
                    WHERE user_id = :success_user_id AND exitoso = 1),
                   '1970-01-01 00:00:00'
               )"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':success_user_id' => $userId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function lastFailureAt(int $userId): ?string
    {
        $stmt = $this->db->prepare(
            'SELECT MAX(created_at) FROM intentos_recuperacion WHERE user_id = ? AND exitoso = 0'
        );
        $stmt->execute([$userId]);
        $value = $stmt->fetchColumn();

        return is_string($value) && $value !== '' ? $value : null;
    }
}
