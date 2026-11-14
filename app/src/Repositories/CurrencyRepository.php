<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

/**
 * Monedas activas por usuario.
 */
final class CurrencyRepository
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
            'SELECT * FROM monedas_usuario WHERE user_id = ?
             ORDER BY es_principal DESC, codigo ASC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function has(int $userId, string $code): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM monedas_usuario WHERE user_id = ? AND codigo = ? LIMIT 1'
        );
        $stmt->execute([$userId, $code]);

        return $stmt->fetchColumn() !== false;
    }

    public function ensure(int $userId, string $code): void
    {
        if ($this->principalForUser($userId) !== null) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO monedas_usuario
                (user_id, codigo, es_principal, created_at, updated_at)
             VALUES (?, ?, 1, NOW(), NOW())'
        );
        $stmt->execute([$userId, $code]);
    }

    public function add(int $userId, string $code): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO monedas_usuario
                (user_id, codigo, es_principal, created_at, updated_at)
             VALUES (?, ?, 0, NOW(), NOW())'
        );
        $stmt->execute([$userId, $code]);
    }

    public function remove(int $userId, string $code): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM monedas_usuario
             WHERE user_id = ? AND codigo = ? AND es_principal = 0'
        );
        $stmt->execute([$userId, $code]);
    }

    public function setPrincipal(int $userId, string $code): void
    {
        $this->db->beginTransaction();

        try {
            $this->db->prepare(
                'UPDATE monedas_usuario SET es_principal = 0, updated_at = NOW() WHERE user_id = ?'
            )->execute([$userId]);
            $this->db->prepare(
                'UPDATE monedas_usuario SET es_principal = 1, updated_at = NOW()
                 WHERE user_id = ? AND codigo = ?'
            )->execute([$userId, $code]);
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function principalForUser(int $userId): ?string
    {
        $stmt = $this->db->prepare(
            'SELECT codigo FROM monedas_usuario
             WHERE user_id = ? AND es_principal = 1 LIMIT 1'
        );
        $stmt->execute([$userId]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (string) $value;
    }
}
