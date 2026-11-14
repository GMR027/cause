<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use Cauce\Services\CurrencyService;
use PDO;

final class PlantillaRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allForUser(int $userId, ?int $limit = null): array
    {
        $sql = 'SELECT p.*, s.nombre AS subcategoria FROM plantillas_gasto p
                LEFT JOIN subcategorias s ON s.id = p.subcategoria_id
                WHERE p.user_id = ?
                ORDER BY p.usos DESC, p.ultimo_uso_at DESC, p.orden ASC, p.id ASC';

        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, min(50, $limit));
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, s.nombre AS subcategoria FROM plantillas_gasto p
             LEFT JOIN subcategorias s ON s.id = p.subcategoria_id
             WHERE p.id = ? AND p.user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function create(
        int $userId,
        string $nombre,
        int $montoCentavos,
        string $moneda,
        string $parte,
        ?int $subcategoriaId,
        ?array $tags,
        int $orden = 0
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO plantillas_gasto
                (user_id, nombre, monto_centavos, monto_principal_centavos, moneda,
                 parte_metodo, subcategoria_id, tags_json, orden, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );

        $stmt->execute([
            $userId,
            $nombre,
            $montoCentavos,
            (new CurrencyService())->toPrincipalForUserId($userId, $montoCentavos, $moneda, gmdate('Y-m-d')),
            $moneda,
            $parte,
            $subcategoriaId,
            $tags === null ? null : json_encode(array_values($tags), JSON_UNESCAPED_UNICODE),
            $orden,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM plantillas_gasto WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }

    public function touchUsage(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE plantillas_gasto SET usos = usos + 1, ultimo_uso_at = NOW(), updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$id]);
    }

    /**
     * @param list<int> $tagIds
     */
    public function updateTagsJson(int $id, array $tagIds): void
    {
        $stmt = $this->db->prepare('UPDATE plantillas_gasto SET tags_json = ? WHERE id = ?');
        $stmt->execute([
            $tagIds === [] ? null : json_encode(array_values($tagIds), JSON_UNESCAPED_UNICODE),
            $id,
        ]);
    }

    /**
     * @return list<array{id: int, tags_json: ?string}>
     */
    public function tagPayloadForUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT id, tags_json FROM plantillas_gasto WHERE user_id = ?');
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }
}
