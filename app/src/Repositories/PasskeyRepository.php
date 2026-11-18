<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

/**
 * Llaves de acceso (passkeys) de cada persona.
 */
final class PasskeyRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM llaves_acceso WHERE user_id = ? ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByCredential(string $credentialId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM llaves_acceso WHERE credencial_id = ? LIMIT 1');
        $stmt->execute([$credentialId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function handleFor(int $userId): ?string
    {
        $stmt = $this->db->prepare('SELECT usuario_handle FROM llaves_acceso WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $handle = $stmt->fetchColumn();

        return $handle === false ? null : (string) $handle;
    }

    public function countFor(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM llaves_acceso WHERE user_id = ?');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    public function create(
        int $userId,
        string $credentialId,
        string $publicKey,
        int $algorithm,
        string $handle,
        int $counter,
        string $name
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO llaves_acceso
                (user_id, credencial_id, llave_publica, algoritmo, usuario_handle, contador, nombre, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$userId, $credentialId, $publicKey, $algorithm, $handle, $counter, $name]);

        return (int) $this->db->lastInsertId();
    }

    public function markUsed(int $id, int $counter): void
    {
        $stmt = $this->db->prepare(
            'UPDATE llaves_acceso SET contador = ?, ultimo_uso_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$counter, $id]);
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM llaves_acceso WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);

        return $stmt->rowCount() > 0;
    }
}
