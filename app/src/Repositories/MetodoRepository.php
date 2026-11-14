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
            'SELECT m.id, m.codigo, m.nombre, m.descripcion_corta, m.contexto_cultural,
                    m.icono, m.es_cultural, m.orden,
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
            if (in_array($method['codigo'], $codes, true)) {
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
            if ($method['codigo'] === $codigo) {
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
}
