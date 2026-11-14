<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

final class SubcategoriaRepository
{
    /** @var array<string, list<string>> */
    private const SEMILLA = [
        'Necesidades' => [
            'Renta/Hipoteca',
            'Servicios',
            'Supermercado',
            'Transporte',
            'Salud',
            'Educación',
            'Seguros',
            'Deudas/Créditos',
            'Cuidado personal',
        ],
        'Deseos' => [
            'Restaurantes/Cafés',
            'Entretenimiento',
            'Suscripciones',
            'Ropa/Compras',
            'Viajes',
            'Hobbies',
            'Regalos',
        ],
    ];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function seedForUser(int $userId): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO subcategorias
                (user_id, nombre, parte_metodo_default, es_semilla, created_at, updated_at)
             VALUES (?, ?, ?, 1, NOW(), NOW())'
        );

        foreach (self::SEMILLA as $parte => $categorias) {
            foreach ($categorias as $nombre) {
                $stmt->execute([$userId, $nombre, $parte]);
            }
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public static function semilla(): array
    {
        return self::SEMILLA;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM subcategorias
             WHERE user_id = ? AND deleted_at IS NULL
             ORDER BY nombre ASC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM subcategorias
             WHERE id = ? AND user_id = ? AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function findByName(int $userId, string $name, ?int $exceptId = null): ?array
    {
        $sql = 'SELECT * FROM subcategorias WHERE user_id = ? AND nombre = ?';
        $params = [$userId, $name];

        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function rememberPart(int $id, int $userId, string $part): void
    {
        $stmt = $this->db->prepare(
            'UPDATE subcategorias SET parte_metodo_actual = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$part, $id, $userId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allWithUsage(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*,
                (SELECT COUNT(*) FROM gastos g
                 WHERE g.subcategoria_id = s.id AND g.user_id = s.user_id
                   AND g.estado <> "cerrado") AS usos
             FROM subcategorias s
             WHERE s.user_id = ? AND s.deleted_at IS NULL
             ORDER BY s.nombre ASC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function create(int $userId, string $name, string $part): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO subcategorias
                (user_id, nombre, parte_metodo_default, parte_metodo_actual, es_semilla,
                 deleted_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, 0, NULL, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                parte_metodo_default = VALUES(parte_metodo_default),
                parte_metodo_actual = VALUES(parte_metodo_actual),
                deleted_at = NULL,
                updated_at = NOW()'
        );
        $stmt->execute([$userId, $name, $part, $part]);

        return (int) $this->db->lastInsertId();
    }

    public function rename(int $id, int $userId, string $name, string $part): void
    {
        $stmt = $this->db->prepare(
            'UPDATE subcategorias SET nombre = ?, parte_metodo_default = ?,
                parte_metodo_actual = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$name, $part, $part, $id, $userId]);
    }

    public function setPart(int $id, int $userId, string $part): void
    {
        $stmt = $this->db->prepare(
            'UPDATE subcategorias
             SET parte_metodo_default = ?, parte_metodo_actual = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$part, $part, $id, $userId]);
    }

    public function softDelete(int $id, int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE subcategorias SET deleted_at = NOW(), updated_at = NOW()
             WHERE id = ? AND user_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$id, $userId]);
    }
}
