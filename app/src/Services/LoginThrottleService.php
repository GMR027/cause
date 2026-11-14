<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Helpers\Client;
use PDO;

/**
 * Límite de intentos de login por email + IP.
 */
final class LoginThrottleService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function tooManyAttempts(string $email, ?string $ip = null): bool
    {
        $window = (int) config('security.login.window_minutes', 15);
        $max = (int) config('security.login.max_attempts', 5);
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE exitoso = 0
               AND (email_hash = ? OR ip <=> ?)
               AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? MINUTE)'
        );
        $statement->execute([$this->hash($email), $ip ?? Client::ip(), $window]);

        return (int) $statement->fetchColumn() >= $max;
    }

    public function record(string $email, bool $success, ?string $ip = null): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO login_attempts (email_hash, ip, exitoso, created_at, updated_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $statement->execute([$this->hash($email), $ip ?? Client::ip(), $success ? 1 : 0]);

        if ($success) {
            $delete = $this->db->prepare(
                'DELETE FROM login_attempts WHERE email_hash = ? AND exitoso = 0'
            );
            $delete->execute([$this->hash($email)]);
        }
    }

    public function prune(): void
    {
        $this->db->exec('DELETE FROM login_attempts WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)');
    }

    private function hash(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }
}
