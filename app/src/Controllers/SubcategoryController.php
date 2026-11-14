<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\View;
use Cauce\Services\AuthService;
use Cauce\Services\GastoService;
use Cauce\Services\SubcategoryService;
use Throwable;

/**
 * Administración de subcategorías.
 */
final class SubcategoryController
{
    public function index(): void
    {
        $user = $this->user();
        $service = new SubcategoryService();
        $categories = (new GastoService())->categoriasGasto($user);
        $allowed = array_map(static fn (array $category): string => (string) $category['nombre'], $categories);
        $grouped = [];

        foreach ($service->list($user) as $subcategory) {
            $part = (string) ($subcategory['parte_metodo_actual'] ?? $subcategory['parte_metodo_default'] ?? '');
            $grouped[$part][] = $subcategory;
        }

        echo View::render('subcategories/index', [
            'title' => __('subcategories.title'),
            'grouped' => $grouped,
            'parts' => array_values(array_unique(array_merge($allowed, array_keys($grouped)))),
            'allowed_parts' => $allowed,
        ], 'layouts/app');
    }

    public function store(): void
    {
        $user = $this->user();

        try {
            $id = (new SubcategoryService())->create(
                $user,
                (string) input('nombre', ''),
                (string) input('parte', ''),
                $this->allowedParts($user)
            );
            // El repositorio hace upsert: si ya existía, no se creó una nueva.
            Flash::success($id > 0 ? 'subcategories.flash.created' : 'subcategories.flash.updated');
        } catch (Throwable $exception) {
            $this->flashError($exception);
        }

        redirect('/subcategorias');
    }

    public function update(): void
    {
        $user = $this->user();

        try {
            (new SubcategoryService())->rename(
                $user,
                (int) input('id', '0'),
                (string) input('nombre', ''),
                (string) input('parte', ''),
                $this->allowedParts($user)
            );
            Flash::success('subcategories.flash.updated');
        } catch (Throwable $exception) {
            $this->flashError($exception);
        }

        redirect('/subcategorias');
    }

    public function delete(): void
    {
        $user = $this->user();

        try {
            (new SubcategoryService())->delete($user, (int) input('id', '0'));
            Flash::success('subcategories.flash.deleted');
        } catch (Throwable $exception) {
            $this->flashError($exception);
        }

        redirect('/subcategorias');
    }

    private function flashError(Throwable $exception): void
    {
        $message = $exception->getMessage();

        $key = match (true) {
            str_contains($message, 'no existe') => 'subcategories.errors.not_found',
            str_contains($message, 'Ya existe') => 'subcategories.errors.duplicate',
            str_contains($message, 'nombre') => 'subcategories.errors.name',
            str_contains($message, 'categoría') => 'subcategories.errors.part',
            default => 'subcategories.errors.generic',
        };

        Flash::error($key);
    }

    /**
     * @return list<string>
     */
    private function allowedParts(array $user): array
    {
        return array_map(
            static fn (array $category): string => (string) $category['nombre'],
            (new GastoService())->categoriasGasto($user)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function user(): array
    {
        $user = AuthService::user();

        if ($user === null) {
            redirect('/login');
        }

        return $user;
    }
}
