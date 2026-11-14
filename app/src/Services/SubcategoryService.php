<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\SubcategoriaRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Gestión de subcategorías del usuario.
 */
final class SubcategoryService
{
    private SubcategoriaRepository $repo;

    public function __construct(?SubcategoriaRepository $repo = null)
    {
        $this->repo = $repo ?? new SubcategoriaRepository();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(array $user): array
    {
        return $this->repo->allWithUsage((int) $user['id']);
    }

    public function create(array $user, string $name, string $part, array $allowedParts): int
    {
        return $this->repo->create(
            (int) $user['id'],
            $this->name($name),
            $this->part($part, $allowedParts)
        );
    }

    public function rename(array $user, int $id, string $name, string $part, array $allowedParts): void
    {
        $this->require($user, $id);
        $name = $this->name($name);
        $part = $this->part($part, $allowedParts);

        if ($this->repo->findByName((int) $user['id'], $name, $id) !== null) {
            throw new InvalidArgumentException('Ya existe una subcategoría con ese nombre.');
        }

        $this->repo->rename($id, (int) $user['id'], $name, $part);
    }

    public function delete(array $user, int $id): void
    {
        $this->require($user, $id);
        $this->repo->softDelete($id, (int) $user['id']);
    }

    /**
     * Alinea las subcategorías con las categorías del método activo.
     *
     * Las semillas usan "Necesidades" y "Deseos"; en métodos como Seis sobres,
     * Cinco frascos o 70/20/10 esos nombres no existen y el filtro del gasto
     * dejaba de funcionar. Se remapean a la categoría controlada que
     * corresponde, respetando coincidencias por nombre.
     *
     * @param list<array<string, mixed>> $categories
     */
    public function reconcileParts(int $userId, array $categories): void
    {
        $names = [];
        $controladas = [];

        foreach ($categories as $category) {
            $name = trim((string) ($category['nombre'] ?? ''));

            if ($name === '') {
                continue;
            }

            $names[] = $name;

            if (($category['tipo'] ?? '') === 'controlada' && ($category['porcentaje'] ?? null) !== null) {
                $controladas[$name] = (float) $category['porcentaje'];
            }
        }

        if ($controladas === []) {
            return;
        }

        arsort($controladas);
        $primary = (string) array_key_first($controladas);
        $secondary = count($controladas) > 1 ? (string) array_key_last($controladas) : $primary;

        foreach ($this->repo->allForUser($userId) as $subcategory) {
            $current = (string) ($subcategory['parte_metodo_actual'] ?? $subcategory['parte_metodo_default'] ?? '');
            $name = (string) $subcategory['nombre'];

            if (in_array($name, array_keys($controladas), true)) {
                $target = $name;
            } elseif (in_array($current, $names, true)) {
                continue;
            } else {
                $target = $current === 'Deseos' ? $secondary : $primary;
            }

            if ($target !== $current) {
                $this->repo->setPart((int) $subcategory['id'], $userId, $target);
            }
        }
    }

    private function require(array $user, int $id): void
    {
        if ($this->repo->findForUser($id, (int) $user['id']) === null) {
            throw new RuntimeException('La subcategoría no existe.');
        }
    }

    private function name(string $name): string
    {
        $name = trim($name);

        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            throw new InvalidArgumentException('El nombre debe tener entre 2 y 80 caracteres.');
        }

        return $name;
    }

    /**
     * @param list<string> $allowedParts
     */
    private function part(string $part, array $allowedParts): string
    {
        $part = trim($part);

        if (!in_array($part, $allowedParts, true)) {
            throw new InvalidArgumentException('La categoría no es válida.');
        }

        return $part;
    }
}
