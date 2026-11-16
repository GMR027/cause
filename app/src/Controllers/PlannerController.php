<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\View;
use Cauce\Repositories\IngresoRepository;
use Cauce\Repositories\PlanRepository;
use Cauce\Services\AuthService;
use Cauce\Services\CurrencyService;
use Cauce\Services\ExpensePlannerService;
use Cauce\Services\MethodService;
use Cauce\Services\MoneyFxService;
use Cauce\Services\OnboardingService;
use Cauce\Services\PeriodoService;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Planeador de gastos: historial de planes calculados desde el próximo ingreso.
 */
final class PlannerController
{
    private const PER_PAGE = 10;

    private ExpensePlannerService $service;

    private PlanRepository $plans;

    public function __construct()
    {
        $this->service = new ExpensePlannerService();
        $this->plans = new PlanRepository();
    }

    public function index(): void
    {
        $user = $this->user();
        $today = $this->today($user);
        $filters = $this->filters();
        $page = max(1, (int) input('page', '1'));
        $total = $this->plans->countForUser((int) $user['id'], $filters);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $plans = $this->plans->allForUser(
            (int) $user['id'],
            $filters,
            self::PER_PAGE,
            ($page - 1) * self::PER_PAGE
        );
        $current = $this->plans->nextForUser((int) $user['id'], $today);

        echo View::render('planner/index', [
            'title' => __('planner.index.title'),
            'current' => $current,
            'currentSnapshot' => $current !== null ? $this->snapshotOf($current) : null,
            'currentStatus' => $current !== null ? $this->statusOf($current, $today) : null,
            'plans' => array_map(
                fn (array $plan): array => $this->decorate($plan, $today),
                $plans
            ),
            'filters' => $filters,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'today' => $today,
            'hasMethod' => (new MethodService())->activeData($user) !== null,
        ], 'layouts/app');
    }

    public function create(): void
    {
        $user = $this->user();

        if ((new MethodService())->activeData($user) === null) {
            Flash::error('planner.errors.no_method');
            redirect('/ajustes/ingreso');
        }

        $today = $this->today($user);
        $principal = (new CurrencyService())->principal($user);
        $source = null;
        $duplicating = (int) ($_GET['duplicar'] ?? 0);

        if ($duplicating > 0) {
            $source = $this->plans->findForUser($duplicating, (int) $user['id']);
        }

        $items = null;

        if ($source !== null) {
            $items = $this->itemsForForm((int) $source['id']);
        }

        $input = [
            'fecha_ingreso' => $today,
            'monto' => $source !== null
                ? number_format((int) $source['monto_centavos'] / 100, 2, '.', '')
                : $this->defaultAmount($user),
            'moneda' => $source !== null ? (string) $source['moneda'] : $principal,
            'notas' => $source !== null ? (string) ($source['notas'] ?? '') : '',
        ];

        if ($source !== null && $items === []) {
            $items = null;
        }

        $plan = $this->service->build($user, $input, $items);

        echo View::render('planner/form', [
            'title' => __('planner.form.title_new'),
            'mode' => 'create',
            'plan' => $plan,
            'planId' => 0,
            'itemsPresent' => $items !== null,
            'duplicatedFrom' => $source,
            'currencies' => currency_options(),
            'today' => $today,
        ], 'layouts/app');
    }

    public function preview(): void
    {
        $user = $this->user();
        $input = $this->inputFromPost();
        $planId = (int) input('plan_id', '0');
        $itemsPresent = array_key_exists('items_present', $_POST);
        $items = $itemsPresent ? $this->itemsFromPost() : null;
        $plan = $this->service->build($user, $input, $items);

        if ($plan['errors'] !== []) {
            $this->renderForm($user, $plan, $planId > 0 ? 'edit' : 'create', $planId, $itemsPresent);
            return;
        }

        echo View::render('planner/preview', [
            'title' => __('planner.preview.title'),
            'plan' => $plan,
            'planId' => $planId,
            'items' => $items ?? [],
            'itemsPresent' => $itemsPresent,
        ], 'layouts/app');
    }

    public function store(): void
    {
        $user = $this->user();
        $itemsPresent = array_key_exists('items_present', $_POST);
        $items = $itemsPresent ? $this->itemsFromPost() : null;
        $plan = $this->service->build($user, $this->inputFromPost(), $items);

        if ($plan['errors'] !== []) {
            $this->renderForm($user, $plan, 'create', 0, $itemsPresent);
            return;
        }

        if ($this->plans->existsForDate((int) $user['id'], (string) $plan['fecha_ingreso'])) {
            Flash::warning('planner.warnings.duplicate_date');
        }

        $id = $this->plans->create($this->rowFromPlan($user, $plan));
        $this->plans->replaceItems($id, $plan['items']);

        MoneyFxService::queue($user, 'plan', (int) $plan['monto_centavos'], (string) $plan['moneda']);

        Flash::success('planner.flash.created');
        redirect('/planeador/ver?id=' . $id);
    }

    public function show(): void
    {
        $user = $this->user();
        $plan = $this->requirePlan((int) ($_GET['id'] ?? 0), $user);
        $today = $this->today($user);
        $snapshot = $this->snapshotOf($plan);
        $editable = (string) $plan['estado'] === 'activo' && (string) $plan['fecha_ingreso'] >= $today;
        $methodChanged = false;
        $active = (new MethodService())->activeData($user);

        if ($active !== null && $plan['metodo_id'] !== null) {
            $methodChanged = (int) $active['method']['id'] !== (int) $plan['metodo_id'];

            // Un reajuste de porcentajes del mismo método también deja el
            // plan desactualizado.
            if (!$methodChanged) {
                $current = array_filter($active['percentages'], static fn (float $v): bool => $v > 0.001);
                $stored = json_decode((string) ($plan['porcentajes_json'] ?? ''), true);
                $saved = array_filter(
                    array_map('floatval', is_array($stored) ? $stored : []),
                    static fn (float $v): bool => $v > 0.001
                );
                ksort($current);
                ksort($saved);
                $methodChanged = array_keys($current) !== array_keys($saved)
                    || array_filter(
                        $current,
                        static fn (float $v, string $k): bool => abs($v - (float) ($saved[$k] ?? 0)) > 0.01,
                        ARRAY_FILTER_USE_BOTH
                    ) !== [];
            }
        }

        echo View::render('planner/show', [
            'title' => __('planner.show.title'),
            'plan' => $plan,
            'snapshot' => $snapshot,
            'status' => $this->statusOf($plan, $today),
            'editable' => $editable,
            'methodChanged' => $methodChanged,
            'items' => $this->itemsForForm((int) $plan['id']),
            'today' => $today,
        ], 'layouts/app');
    }

    public function edit(): void
    {
        $user = $this->user();
        $plan = $this->requirePlan((int) ($_GET['id'] ?? 0), $user);
        $today = $this->today($user);

        if ((string) $plan['estado'] !== 'activo' || (string) $plan['fecha_ingreso'] < $today) {
            Flash::error('planner.errors.not_editable');
            redirect('/planeador/ver?id=' . (int) $plan['id']);
        }

        $items = $this->itemsForForm((int) $plan['id']);
        $input = [
            'fecha_ingreso' => (string) $plan['fecha_ingreso'],
            'monto' => number_format((int) $plan['monto_centavos'] / 100, 2, '.', ''),
            'moneda' => (string) $plan['moneda'],
            'notas' => (string) ($plan['notas'] ?? ''),
        ];
        $built = $this->service->build($user, $input, $items);

        echo View::render('planner/form', [
            'title' => __('planner.form.title_edit'),
            'mode' => 'edit',
            'plan' => $built,
            'planId' => (int) $plan['id'],
            'itemsPresent' => true,
            'duplicatedFrom' => null,
            'currencies' => currency_options(),
            'today' => $today,
        ], 'layouts/app');
    }

    public function update(): void
    {
        $user = $this->user();
        $plan = $this->requirePlan((int) input('plan_id', '0'), $user);
        $today = $this->today($user);

        if ((string) $plan['estado'] !== 'activo' || (string) $plan['fecha_ingreso'] < $today) {
            Flash::error('planner.errors.not_editable');
            redirect('/planeador/ver?id=' . (int) $plan['id']);
        }

        $itemsPresent = array_key_exists('items_present', $_POST);
        $items = $itemsPresent ? $this->itemsFromPost() : [];
        $built = $this->service->build($user, $this->inputFromPost(), $items);

        if ($built['errors'] !== []) {
            $this->renderForm($user, $built, 'edit', (int) $plan['id'], true);
            return;
        }

        $this->plans->update((int) $plan['id'], (int) $user['id'], $this->rowFromPlan($user, $built));
        $this->plans->replaceItems((int) $plan['id'], $built['items']);

        MoneyFxService::queue($user, 'plan', (int) $built['monto_centavos'], (string) $built['moneda']);

        Flash::success('planner.flash.updated');
        redirect('/planeador/ver?id=' . (int) $plan['id']);
    }

    public function recalculate(): void
    {
        $user = $this->user();
        $plan = $this->requirePlan((int) input('plan_id', '0'), $user);
        $today = $this->today($user);

        if ((string) $plan['estado'] !== 'activo' || (string) $plan['fecha_ingreso'] < $today) {
            Flash::error('planner.errors.not_editable');
            redirect('/planeador/ver?id=' . (int) $plan['id']);
        }

        $items = $this->itemsForForm((int) $plan['id']);
        $input = [
            'fecha_ingreso' => (string) $plan['fecha_ingreso'],
            'monto' => number_format((int) $plan['monto_centavos'] / 100, 2, '.', ''),
            'moneda' => (string) $plan['moneda'],
            'notas' => (string) ($plan['notas'] ?? ''),
        ];
        $built = $this->service->build($user, $input, $items);

        if ($built['errors'] !== []) {
            Flash::error($built['errors'][0]);
            redirect('/planeador/ver?id=' . (int) $plan['id']);
        }

        $this->plans->update((int) $plan['id'], (int) $user['id'], $this->rowFromPlan($user, $built));
        $this->plans->replaceItems((int) $plan['id'], $built['items']);

        Flash::success('planner.flash.recalculated');
        redirect('/planeador/ver?id=' . (int) $plan['id']);
    }

    public function complete(): void
    {
        $user = $this->user();
        $plan = $this->requirePlan((int) input('plan_id', '0'), $user);

        if ((string) $plan['estado'] === 'completado') {
            Flash::info('planner.flash.already_completed');
            redirect('/planeador/ver?id=' . (int) $plan['id']);
        }

        $this->plans->markCompleted((int) $plan['id'], (int) $user['id']);

        Flash::success('planner.flash.completed');
        redirect('/planeador/ver?id=' . (int) $plan['id']);
    }

    public function destroy(): void
    {
        $user = $this->user();
        $plan = $this->requirePlan((int) input('plan_id', '0'), $user);
        $this->plans->delete((int) $plan['id'], (int) $user['id']);

        Flash::success('planner.flash.deleted');
        redirect('/planeador');
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private function rowFromPlan(array $user, array $plan): array
    {
        return [
            'user_id' => (int) $user['id'],
            'fecha_ingreso' => (string) $plan['fecha_ingreso'],
            'monto_centavos' => (int) $plan['monto_centavos'],
            'moneda' => (string) $plan['moneda'],
            'monto_principal_centavos' => (int) $plan['monto_principal_centavos'],
            'metodo_id' => $plan['metodo_id'],
            'metodo_codigo' => $plan['metodo_codigo'],
            'metodo_nombre' => $plan['metodo_nombre'],
            'porcentajes' => $plan['porcentajes'],
            'resumen' => $this->snapshot($plan),
            'notas' => $plan['notas'],
        ];
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private function snapshot(array $plan): array
    {
        return [
            'generated_at' => gmdate('c'),
            'summary' => $plan['summary'],
            'sections' => $plan['sections'],
            'commitments' => $plan['commitments'],
            'debts' => $plan['debts'],
            'warnings' => $plan['warnings'],
            'dias_restantes' => $plan['dias_restantes'],
            'monto_principal_centavos' => $plan['monto_principal_centavos'],
            'metodo_nombre' => $plan['metodo_nombre'],
            'porcentajes' => $plan['porcentajes'],
        ];
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private function snapshotOf(array $plan): array
    {
        $decoded = json_decode((string) ($plan['resumen_json'] ?? ''), true);
        $snapshot = is_array($decoded) ? $decoded : [];
        $snapshot['summary'] = is_array($snapshot['summary'] ?? null) ? $snapshot['summary'] : [];
        $snapshot['sections'] = is_array($snapshot['sections'] ?? null) ? $snapshot['sections'] : [];
        $snapshot['commitments'] = is_array($snapshot['commitments'] ?? null) ? $snapshot['commitments'] : [];
        $snapshot['debts'] = is_array($snapshot['debts'] ?? null) ? $snapshot['debts'] : [];
        $snapshot['warnings'] = is_array($snapshot['warnings'] ?? null) ? $snapshot['warnings'] : [];

        return $snapshot;
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private function decorate(array $plan, string $today): array
    {
        $plan['estado_visual'] = $this->statusOf($plan, $today);
        $decoded = json_decode((string) ($plan['resumen_json'] ?? ''), true);
        $plan['resumen'] = is_array($decoded) ? $decoded : [];

        return $plan;
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function statusOf(array $plan, string $today): string
    {
        if ((string) $plan['estado'] === 'completado') {
            return 'completado';
        }

        return (string) $plan['fecha_ingreso'] < $today ? 'vencido' : 'activo';
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function requirePlan(int $id, array $user): array
    {
        $plan = $id > 0 ? $this->plans->findForUser($id, (int) $user['id']) : null;

        if ($plan === null) {
            Flash::error('planner.errors.not_found');
            redirect('/planeador');
        }

        return $plan;
    }

    /**
     * @return list<array{seccion: string, concepto: string, monto: string}>
     */
    private function itemsFromPost(): array
    {
        $raw = $_POST['items'] ?? [];
        $items = [];

        foreach (is_array($raw) ? $raw : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = [
                'seccion' => trim((string) ($row['seccion'] ?? '')),
                'concepto' => trim((string) ($row['concepto'] ?? '')),
                'monto' => trim((string) ($row['monto'] ?? '')),
            ];
        }

        return $items;
    }

    /**
     * @return list<array{seccion: string, concepto: string, monto_centavos: int}>
     */
    private function itemsForForm(int $planId): array
    {
        return array_map(static fn (array $item): array => [
            'seccion' => (string) $item['seccion'],
            'concepto' => (string) $item['concepto'],
            'monto_centavos' => (int) $item['monto_centavos'],
        ], $this->plans->itemsForPlan($planId));
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function renderForm(
        array $user,
        array $plan,
        string $mode,
        int $planId,
        bool $itemsPresent
    ): void {
        echo View::render('planner/form', [
            'title' => $mode === 'edit' ? __('planner.form.title_edit') : __('planner.form.title_new'),
            'mode' => $mode,
            'plan' => $plan,
            'planId' => $planId,
            'itemsPresent' => $itemsPresent,
            'duplicatedFrom' => null,
            'currencies' => currency_options(),
            'today' => $this->today($user),
        ], 'layouts/app');
    }

    /**
     * @return array<string, mixed>
     */
    private function inputFromPost(): array
    {
        return [
            'fecha_ingreso' => (string) input('fecha_ingreso', ''),
            'monto' => (string) input('monto', ''),
            'moneda' => (string) input('moneda', ''),
            'notas' => (string) input('notas', ''),
        ];
    }

    /**
     * @return array{estado: string, momento: string}
     */
    private function filters(): array
    {
        $estado = (string) input('estado', '');
        $momento = (string) input('momento', '');

        return [
            'estado' => in_array($estado, ['activo', 'completado'], true) ? $estado : '',
            'momento' => in_array($momento, ['proximos', 'pasados'], true) ? $momento : '',
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    private function defaultAmount(array $user): string
    {
        $last = (new IngresoRepository())->lastFixedAmount((int) $user['id']);
        $cents = $last;

        if ($cents === null) {
            $onboarding = (new OnboardingService())->state($user);
            $cents = (int) ($onboarding['monto_centavos'] ?? (int) env('ONBOARDING_DEFAULT_INCOME_CENTS', 1000000));
        }

        return number_format(max(0, (int) $cents) / 100, 2, '.', '');
    }

    /**
     * @param array<string, mixed> $user
     */
    private function today(array $user): string
    {
        $timezone = (new PeriodoService())->userTimezone($user);

        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone($timezone)
            ->format('Y-m-d');
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
