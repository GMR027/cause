<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\View;
use Cauce\Repositories\GastoRepository;
use Cauce\Repositories\TagRepository;
use Cauce\Services\AuthService;
use Cauce\Services\CurrencyService;
use Cauce\Services\FinanceSupportService;
use Cauce\Services\GastoService;
use Cauce\Services\SearchService;
use DateTimeImmutable;
use Throwable;

/**
 * Búsqueda global con filtros combinables.
 */
final class SearchController
{
    public function index(): void
    {
        $user = $this->user();
        $service = new SearchService();
        $gastos = new GastoService();
        $current = $gastos->periodoActual($user);
        $periods = (new GastoRepository())->distinctPeriods((int) $user['id']);
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

        $filters = $this->filters($user);
        $hasQuery = trim((string) ($filters['q'] ?? '')) !== ''
            || trim((string) ($filters['categoria'] ?? '')) !== ''
            || ($filters['periodo_inicio'] ?? '') !== ''
            || ($filters['fecha_inicio'] ?? '') !== ''
            || ($filters['fecha_fin'] ?? '') !== ''
            || ($filters['tag_id'] ?? 0) > 0
            || ($filters['medio'] ?? '') !== ''
            || isset($filters['monto_min'])
            || isset($filters['monto_max']);
        $results = $hasQuery ? $service->search($user, $filters) : [];
        $tagRepo = new TagRepository();

        echo View::render('search/index', [
            'title' => __('search.title'),
            'filters' => $filters,
            'results' => $results,
            'hasQuery' => $hasQuery,
            'periods' => $periods,
            'categories' => $gastos->categoriasActivas($user),
            'tags' => $tagRepo->allForUser((int) $user['id']),
            'currency' => (string) ($user['moneda_activa'] ?? 'MXN'),
            'total' => $this->totalInPrincipal($user, $results),
        ], 'layouts/app');
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function filters(array $user): array
    {
        $filters = [];
        $q = input('q', '') ?? '';

        if ($q !== '') {
            $filters['q'] = $q;
        }

        $period = input('periodo', '') ?? '';

        if ($period !== '' && str_contains($period, '|')) {
            [$inicio, $fin] = explode('|', $period, 2);
            $filters['periodo_inicio'] = $inicio;
            $filters['periodo_fin'] = $fin;
        } else {
            $support = new FinanceSupportService();
            $from = input('desde', '') ?? '';
            $to = input('hasta', '') ?? '';

            try {
                if ($from !== '') {
                    $filters['fecha_inicio'] = $support->toUtc($from, $user)->format('Y-m-d H:i:s');
                }

                if ($to !== '') {
                    $end = new DateTimeImmutable($to . ' 23:59:59', $support->ago($user));
                    $filters['fecha_fin'] = $end->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                }
            } catch (Throwable) {
                unset($filters['fecha_inicio'], $filters['fecha_fin']);
            }
        }

        foreach (['parte' => 'categoria', 'tag' => 'tag_id', 'medio' => 'medio'] as $inputKey => $filterKey) {
            $value = input($inputKey, '') ?? '';

            if ($value !== '') {
                $filters[$filterKey] = $filterKey === 'tag_id' ? (int) $value : $value;
            }
        }

        $min = parse_money_cents(input('monto_min', '') ?? '');
        $max = parse_money_cents(input('monto_max', '') ?? '');

        if ($min !== null) {
            $filters['monto_min'] = $min;
        }

        if ($max !== null) {
            $filters['monto_max'] = $max;
        }

        return $filters;
    }

    /**
     * Suma resultados heterogéneos en la moneda principal del usuario.
     *
     * La búsqueda mezcla gastos, ingresos, ahorro, deudas, etc. Sumar
     * "monto_centavos" a secas mezclaba monedas distintas.
     *
     * @param array<string, mixed> $user
     * @param list<array<string, mixed>> $results
     */
    private function totalInPrincipal(array $user, array $results): int
    {
        $principal = (string) ($user['moneda_activa'] ?? 'MXN');
        $currencies = new CurrencyService();
        $total = 0;

        foreach ($results as $row) {
            $cents = (int) ($row['monto_centavos'] ?? 0);
            $currency = (string) ($row['moneda'] ?? $principal);

            if ($cents <= 0) {
                continue;
            }

            if ($currency === $principal) {
                $total += $cents;
                continue;
            }

            try {
                $date = substr((string) ($row['fecha'] ?? gmdate('Y-m-d')), 0, 10);
                $total += (int) $currencies->toPrincipal($user, $cents, $currency, $date)['centavos'];
            } catch (Throwable) {
                // Si no hay tasa, se omite del total; el resultado sigue visible.
            }
        }

        return $total;
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
