<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

final class UsuarioMetodoRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * Cierra la configuración vigente e inserta la nueva.
     *
     * @param array<string, int> $porcentajes
     */
    public function activate(int $userId, int $metodoId, array $porcentajes): int
    {
        $close = $this->db->prepare(
            'UPDATE usuario_metodos
             SET vigente_hasta = NOW(), updated_at = NOW()
             WHERE user_id = ? AND vigente_hasta IS NULL'
        );
        $close->execute([$userId]);

        $stmt = $this->db->prepare(
            'INSERT INTO usuario_metodos
                (user_id, metodo_id, porcentajes_json, vigente_desde, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW(), NOW())'
        );

        $stmt->execute([
            $userId,
            $metodoId,
            json_encode($porcentajes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activeForUser(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM usuario_metodos
             WHERE user_id = ? AND vigente_hasta IS NULL
             ORDER BY vigente_desde DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }
}
