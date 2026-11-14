<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\View;
use Cauce\Repositories\GastoRepository;
use Cauce\Repositories\PlantillaRepository;
use Cauce\Repositories\SubcategoriaRepository;
use Cauce\Repositories\TagRepository;
use Cauce\Services\AuthService;
use Cauce\Services\GastoService;
use InvalidArgumentException;

/**
 * Plantillas de gasto (favoritos).
 */
final class TemplateController
{
    public function index(): void
    {
        $user = $this->user();
        $service = new GastoService();
        $tagRepo = new TagRepository();
        $templates = (new PlantillaRepository())->allForUser((int) $user['id']);
        $tags = $tagRepo->allForUser((int) $user['id']);

        foreach ($templates as &$template) {
            $ids = [];

            if (!empty($template['tags_json'])) {
                $decoded = json_decode((string) $template['tags_json'], true);
                $ids = is_array($decoded) ? array_map('intval', $decoded) : [];
            }

            $template['tag_ids'] = $ids;
            $template['tag_names'] = [];

            foreach ($tags as $tag) {
                if (in_array((int) $tag['id'], $ids, true)) {
                    $template['tag_names'][] = (string) $tag['nombre_normalizado'];
                }
            }
        }
        unset($template);

        echo View::render('ajustes/plantillas', [
            'title' => __('expenses.templates.title'),
            'templates' => $templates,
            'tags' => $tags,
            'categories' => $service->categoriasGasto($user),
            'subcategories' => (new SubcategoriaRepository())->allForUser((int) $user['id']),
        ], 'layouts/app');
    }

    public function store(): void
    {
        $user = $this->user();
        $service = new GastoService();
        $categories = array_column($service->categoriasGasto($user), 'nombre');
        $name = trim((string) input('nombre', ''));
        $amount = parse_money_cents((string) input('monto', ''));
        $part = (string) input('parte_metodo', '');
        $subcategoryId = input('subcategoria_id', '');
        $subcategoryId = $subcategoryId !== '' ? (int) $subcategoryId : null;

        if ($name === '') {
            Flash::error('expenses.errors.template_name');
            redirect('/ajustes/plantillas');
        }

        if ($amount === null || $amount <= 0) {
            Flash::error('expenses.errors.amount');
            redirect('/ajustes/plantillas');
        }

        if (!in_array($part, $categories, true)) {
            Flash::error('expenses.errors.part');
            redirect('/ajustes/plantillas');
        }

        if ($subcategoryId !== null && (new SubcategoriaRepository())->findForUser($subcategoryId, (int) $user['id']) === null) {
            $subcategoryId = null;
        }

        $tagIds = $this->validTagIds($user);

        try {
            (new PlantillaRepository())->create(
                (int) $user['id'],
                mb_substr($name, 0, 120),
                $amount,
                (string) input('moneda', $user['moneda_activa'] ?? 'MXN'),
                $part,
                $subcategoryId,
                $tagIds
            );
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage() === 'sin_tasa' ? 'currency.errors.no_rate' : 'expenses.errors.generic');
            redirect('/ajustes/plantillas');
        }

        Flash::success('expenses.flash.template_saved');
        redirect('/ajustes/plantillas');
    }

    public function delete(): void
    {
        $user = $this->user();
        $id = (int) ($_POST['id'] ?? 0);
        $repo = new PlantillaRepository();

        if ($repo->findForUser($id, (int) $user['id']) === null) {
            Flash::error('expenses.errors.template_missing');
            redirect('/ajustes/plantillas');
        }

        $repo->delete($id, (int) $user['id']);
        Flash::success('expenses.flash.template_deleted');
        redirect('/ajustes/plantillas');
    }

    public function fromGasto(): void
    {
        $user = $this->user();
        $gastoId = (int) ($_POST['gasto_id'] ?? 0);
        $gasto = (new GastoRepository())->findForUser($gastoId, (int) $user['id']);

        if ($gasto === null) {
            Flash::error('expenses.errors.not_found');
            redirect('/movimientos');
        }

        $tagRepo = new TagRepository();
        $tagIds = array_map(
            static fn (array $tag): int => (int) $tag['id'],
            $tagRepo->forGasto($gastoId)
        );
        $name = trim((string) input('nombre', ''));

        if ($name === '') {
            $name = (string) ($gasto['subcategoria_nombre'] ?? $gasto['parte_metodo']);
        }

        try {
            (new PlantillaRepository())->create(
                (int) $user['id'],
                mb_substr($name, 0, 120),
                (int) $gasto['monto_centavos'],
                (string) $gasto['moneda'],
                (string) $gasto['parte_metodo'],
                $gasto['subcategoria_id'] !== null ? (int) $gasto['subcategoria_id'] : null,
                $tagIds
            );
        } catch (InvalidArgumentException) {
            Flash::error('expenses.errors.generic');
            redirect('/gastos/ver?id=' . $gastoId);
        }

        Flash::success('expenses.flash.template_saved');
        redirect('/gastos/ver?id=' . $gastoId);
    }

    /**
     * @return list<int>
     */
    private function validTagIds(array $user): array
    {
        $input = $_POST['tags'] ?? [];
        $input = is_array($input) ? array_map('intval', $input) : [];
        $valid = array_map(
            static fn (array $tag): int => (int) $tag['id'],
            (new TagRepository())->allForUser((int) $user['id'])
        );

        return array_values(array_intersect($input, $valid));
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
