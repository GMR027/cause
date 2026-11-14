<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\View;
use Cauce\Repositories\GastoRepository;
use Cauce\Repositories\SubcategoriaRepository;
use Cauce\Repositories\TagRepository;
use Cauce\Services\AuthService;
use Cauce\Services\GastoService;

/**
 * Lista de movimientos con filtros y búsqueda.
 */
final class MovementController
{
    public function index(): void
    {
        $user = $this->user();
        $repo = new GastoRepository();
        $service = new GastoService();
        $current = $service->periodoActual($user);
        $filters = [];
        $periods = $repo->distinctPeriods((int) $user['id']);
        $hasCurrent = false;

        foreach ($periods as $item) {
            if ($item['periodo_inicio'] === $current['inicio'] && $item['periodo_fin'] === $current['fin']) {
                $hasCurrent = true;
                break;
            }
        }

        if (!$hasCurrent) {
            array_unshift($periods, [
                'periodo_inicio' => $current['inicio'],
                'periodo_fin' => $current['fin'],
                'total' => 0,
            ]);
        }

        $periodo = input('periodo');

        if ($periodo === null) {
            $periodo = $current['inicio'] . '|' . $current['fin'];
        }

        if ($periodo !== 'todos' && str_contains($periodo, '|')) {
            [$inicio, $fin] = explode('|', $periodo, 2);
            $filters['periodo_inicio'] = $inicio;
            $filters['periodo_fin'] = $fin;
        }

        foreach (['parte' => 'parte_metodo', 'subcategoria' => 'subcategoria_id', 'tag' => 'tag_id', 'medio' => 'medio_pago'] as $inputKey => $filterKey) {
            $value = input($inputKey, '') ?? '';

            if ($value !== '') {
                $filters[$filterKey] = $value;
            }
        }

        $q = input('q', '') ?? '';

        if ($q !== '') {
            $filters['q'] = $q;
        }

        $rows = $repo->listForUser((int) $user['id'], $filters);
        $tagRepo = new TagRepository();
        $tagMap = $tagRepo->forGastos(array_column($rows, 'id'));
        $period = $service->periodoActual($user);
        $editable = [];

        foreach ($rows as $row) {
            $editable[(int) $row['id']] = (string) $row['periodo_inicio'] === $period['inicio']
                && (string) $row['periodo_fin'] === $period['fin']
                && ($row['estado'] ?? '') !== 'cerrado';
        }

        echo View::render('app/movimientos', [
            'title' => __('nav.movimientos'),
            'rows' => $rows,
            'tagMap' => $tagMap,
            'tags' => $tagRepo->allForUser((int) $user['id']),
            'periods' => $periods,
            'periodoValue' => $periodo,
            'categories' => $service->categoriasGasto($user),
            'subcategories' => (new SubcategoriaRepository())->allForUser((int) $user['id']),
            'editable' => $editable,
            'filters' => $filters,
            'total' => array_sum(array_map(
                static fn (array $row): int => (int) ($row['monto_principal_centavos'] ?? $row['monto_centavos']),
                $rows
            )),
        ], 'layouts/app');
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
