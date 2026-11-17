<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

/**
 * Versiones publicadas de la app Android y teléfonos que la usan.
 */
final class AndroidAppRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * La versión vigente: la de mayor version_code.
     *
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        $row = $this->db->query('SELECT * FROM app_android_versiones ORDER BY version_code DESC, id DESC LIMIT 1')->fetch();

        return is_array($row) ? $row : null;
    }

    public function create(string $version, int $code, string $archivo, int $tamano, string $sha256, ?string $notas): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO app_android_versiones (version, version_code, archivo, tamano, sha256, notas) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$version, $code, $archivo, $tamano, $sha256, $notas]);

        return (int) $this->db->lastInsertId();
    }

    public function touchDevice(string $instalacionId, string $version, ?int $userId, ?string $modelo, ?string $android): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO dispositivos_android (instalacion_id, version, user_id, modelo, version_android)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                version = VALUES(version),
                user_id = COALESCE(VALUES(user_id), user_id),
                modelo = COALESCE(VALUES(modelo), modelo),
                version_android = COALESCE(VALUES(version_android), version_android),
                ultima_vez = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$instalacionId, $version, $userId, $modelo, $android]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function devices(int $limit = 200): array
    {
        $stmt = $this->db->prepare(
            'SELECT d.*, u.alias, u.email
               FROM dispositivos_android d
               LEFT JOIN users u ON u.id = d.user_id
              ORDER BY d.ultima_vez DESC
              LIMIT ' . max(1, $limit)
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
