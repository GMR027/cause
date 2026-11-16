<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

final class MetodoRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allWithCategorias(): array
    {
        $stmt = $this->db->query(
            'SELECT m.id, m.user_id, m.codigo, m.nombre, m.descripcion_corta, m.contexto_cultural,
                    m.icono, m.es_cultural, m.orden, m.archivado_at,
                    c.id AS categoria_id, c.nombre AS categoria_nombre,
                    c.porcentaje, c.tipo, c.orden AS categoria_orden
             FROM metodos m
             LEFT JOIN metodo_categorias c ON c.metodo_id = m.id
             ORDER BY m.orden ASC, m.id ASC, c.orden ASC, c.id ASC'
        );

        $metodos = [];

        foreach ($stmt->fetchAll() as $row) {
            $id = (int) $row['id'];

            if (!isset($metodos[$id])) {
                $metodos[$id] = [
                    'id' => $id,
                    'user_id' => $row['user_id'] !== null ? (int) $row['user_id'] : null,
                    'archivado' => $row['archivado_at'] !== null,
                    'codigo' => (string) $row['codigo'],
                    'nombre' => (string) $row['nombre'],
                    'descripcion_corta' => (string) $row['descripcion_corta'],
                    'contexto_cultural' => $row['contexto_cultural'] !== null ? (string) $row['contexto_cultural'] : null,
                    'icono' => $row['icono'] !== null ? (string) $row['icono'] : 'bi-pie-chart',
                    'es_cultural' => (int) $row['es_cultural'] === 1,
                    'orden' => (int) $row['orden'],
                    'categorias' => [],
                ];
            }

            if ($row['categoria_id'] !== null) {
                $metodos[$id]['categorias'][] = [
                    'id' => (int) $row['categoria_id'],
                    'nombre' => (string) $row['categoria_nombre'],
                    'porcentaje' => $row['porcentaje'] !== null ? (int) $row['porcentaje'] : null,
                    'tipo' => (string) $row['tipo'],
                    'orden' => (int) $row['categoria_orden'],
                ];
            }
        }

        return array_values($metodos);
    }

    /**
     * @param list<string> $codes
     * @return list<array<string, mixed>>
     */
    public function byCodes(array $codes): array
    {
        $codes = array_values(array_filter($codes, 'is_string'));

        if ($codes === []) {
            return [];
        }

        $byCode = [];

        foreach ($this->allWithCategorias() as $method) {
            if ($method['user_id'] === null && in_array($method['codigo'], $codes, true)) {
                $byCode[$method['codigo']] = $method;
            }
        }

        $ordered = [];

        foreach ($codes as $code) {
            if (isset($byCode[$code])) {
                $ordered[] = $byCode[$code];
            }
        }

        return $ordered;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByCodigo(string $codigo): ?array
    {
        foreach ($this->allWithCategorias() as $method) {
            if ($method['user_id'] === null && $method['codigo'] === $codigo) {
                return $method;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        foreach ($this->allWithCategorias() as $method) {
            if ((int) $method['id'] === $id) {
                return $method;
            }
        }

        return null;
    }

    /**
     * Métodos que la persona puede elegir: los del catálogo indicados en
     * $codes (en ese orden) y después los suyos sin archivar.
     *
     * @param list<string> $codes
     * @return list<array<string, mixed>>
     */
    public function selectableFor(int $userId, array $codes): array
    {
        $own = array_values(array_filter(
            $this->allWithCategorias(),
            static fn (array $method): bool => $method['user_id'] === $userId && !$method['archivado']
        ));

        return array_merge($this->byCodes($codes), $own);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findOwned(int $id, int $userId): ?array
    {
        $method = $this->findById($id);

        if ($method === null || $method['user_id'] !== $userId || $method['archivado']) {
            return null;
        }

        return $method;
    }

    /**
     * @param list<array{nombre: string, tipo: string, porcentaje: int}> $categorias
     */
    public function createCustom(int $userId, string $nombre, array $categorias): int
    {
        $codigo = 'u' . $userId . '_' . bin2hex(random_bytes(6));
        $stmt = $this->db->prepare(
            'INSERT INTO metodos
                (user_id, codigo, nombre, descripcion_corta, contexto_cultural, icono, es_cultural, orden, created_at, updated_at)
             VALUES (?, ?, ?, ?, NULL, ?, 0, 100, NOW(), NOW())'
        );
        $stmt->execute([$userId, $codigo, $nombre, '', 'bi-sliders2']);
        $id = (int) $this->db->lastInsertId();

        $this->insertCategorias($id, $categorias);

        return $id;
    }

    /**
     * Reemplaza nombre y categorías de un método propio. Las categorías que
     * traen `id` se actualizan (para conservar su identidad al renombrar);
     * las demás se crean y las que ya no vienen se eliminan.
     *
     * @param list<array{id?: int|null, nombre: string, tipo: string, porcentaje: int}> $categorias
     * @return array<string, string> Renombres aplicados: nombre anterior => nombre nuevo.
     */
    public function updateCustom(int $id, int $userId, string $nombre, array $categorias): array
    {
        $method = $this->findOwned($id, $userId);

        if ($method === null) {
            return [];
        }

        $this->db->prepare('UPDATE metodos SET nombre = ?, updated_at = NOW() WHERE id = ? AND user_id = ?')
            ->execute([$nombre, $id, $userId]);

        $existing = [];

        foreach ($method['categorias'] as $categoria) {
            $existing[(int) $categoria['id']] = (string) $categoria['nombre'];
        }

        $keep = [];
        $renames = [];

        foreach ($categorias as $categoria) {
            $catId = (int) ($categoria['id'] ?? 0);

            if ($catId > 0 && isset($existing[$catId])) {
                $keep[] = $catId;

                if ($existing[$catId] !== $categoria['nombre']) {
                    $renames[$existing[$catId]] = $categoria['nombre'];
                }
            }
        }

        $removed = array_diff(array_keys($existing), $keep);

        if ($removed !== []) {
            $placeholders = implode(',', array_fill(0, count($removed), '?'));
            $this->db->prepare("DELETE FROM metodo_categorias WHERE metodo_id = ? AND id IN ($placeholders)")
                ->execute(array_merge([$id], array_values($removed)));
        }

        // Nombres temporales para que los renombres cruzados no choquen con
        // la llave única (metodo_id, nombre).
        if ($keep !== []) {
            $placeholders = implode(',', array_fill(0, count($keep), '?'));
            $this->db->prepare(
                "UPDATE metodo_categorias SET nombre = CONCAT('__tmp_', id) WHERE metodo_id = ? AND id IN ($placeholders)"
            )->execute(array_merge([$id], $keep));
        }

        $update = $this->db->prepare(
            'UPDATE metodo_categorias SET nombre = ?, tipo = ?, porcentaje = ?, orden = ?, updated_at = NOW()
             WHERE id = ? AND metodo_id = ?'
        );
        $insert = $this->db->prepare(
            'INSERT INTO metodo_categorias (metodo_id, nombre, porcentaje, tipo, orden, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
        );

        foreach (array_values($categorias) as $index => $categoria) {
            $catId = (int) ($categoria['id'] ?? 0);

            if ($catId > 0 && in_array($catId, $keep, true)) {
                $update->execute([
                    $categoria['nombre'],
                    $categoria['tipo'],
                    $categoria['porcentaje'],
                    $index + 1,
                    $catId,
                    $id,
                ]);
            } else {
                $insert->execute([$id, $categoria['nombre'], $categoria['porcentaje'], $categoria['tipo'], $index + 1]);
            }
        }

        return $renames;
    }

    public function archiveCustom(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE metodos SET archivado_at = NOW(), updated_at = NOW()
             WHERE id = ? AND user_id = ? AND archivado_at IS NULL'
        );
        $stmt->execute([$id, $userId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param list<array{nombre: string, tipo: string, porcentaje: int}> $categorias
     */
    private function insertCategorias(int $metodoId, array $categorias): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO metodo_categorias (metodo_id, nombre, porcentaje, tipo, orden, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
        );

        foreach (array_values($categorias) as $index => $categoria) {
            $stmt->execute([$metodoId, $categoria['nombre'], $categoria['porcentaje'], $categoria['tipo'], $index + 1]);
        }
    }
}
