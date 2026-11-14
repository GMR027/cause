<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use InvalidArgumentException;
use PDO;

/**
 * Lectura y escritura de portada_path en las tablas que admiten portada.
 */
final class CoverRepository
{
    private const TABLES = [
        'lista_deseos',
        'metas_ahorro',
        'cuentas_ahorro',
        'deudas',
        'inversiones_posiciones',
    ];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * La ruta guardada (null si no tiene portada) o false si el registro no
     * existe o no pertenece al usuario.
     */
    public function currentPath(string $table, int $id, int $userId): string|null|false
    {
        $table = $this->guard($table);
        $stmt = $this->db->prepare("SELECT portada_path FROM `{$table}` WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return false;
        }

        return $row['portada_path'] !== null && $row['portada_path'] !== '' ? (string) $row['portada_path'] : null;
    }

    public function setPath(string $table, int $id, int $userId, ?string $path): void
    {
        $table = $this->guard($table);
        $stmt = $this->db->prepare("UPDATE `{$table}` SET portada_path = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$path, $id, $userId]);
    }

    private function guard(string $table): string
    {
        if (!in_array($table, self::TABLES, true)) {
            throw new InvalidArgumentException('Tabla sin portada.');
        }

        return $table;
    }
}
