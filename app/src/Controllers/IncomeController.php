<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\Logger;
use Cauce\Helpers\View;
use Cauce\Repositories\AsignacionRepository;
use Cauce\Repositories\IngresoRepository;
use Cauce\Repositories\MetodoRepository;
use Cauce\Repositories\UsuarioMetodoRepository;
use Cauce\Repositories\DebtRepository;
use Cauce\Repositories\GastoRepository;
use Cauce\Repositories\RecurringExpenseRepository;
use Cauce\Repositories\SavingsRepository;
use Cauce\Repositories\WishlistRepository;
use Cauce\Services\AuthService;
use Cauce\Services\DebtService;
use Cauce\Services\IngresoService;
use Cauce\Services\OnboardingService;
use Cauce\Services\PeriodoService;
use Throwable;

/**
 * Flujo de registro de ingreso: monto, desglose y acreditación.
 */
final class IncomeController
{
    private IngresoService $service;

    public function __construct()
    {
        $this->service = new IngresoService();
    }

    public function create(): void
    {
        $user = $this->user();
        $active = (new UsuarioMetodoRepository())->activeForUser((int) $user['id']);

        if ($active === null) {
            Flash::error('income.errors.no_method');
            redirect('/ajustes/ingreso');
        }

        $method = (new MetodoRepository())->findById((int) $active['metodo_id']);

        if ($method === null) {
            Flash::error('income.errors.no_method');
            redirect('/ajustes/ingreso');
        }

        $onboarding = (new OnboardingService())->state($user);
        $lastFixed = (new IngresoRepository())->lastFixedAmount((int) $user['id']);
        $defaultAmount = $lastFixed ?? (int) ($onboarding['monto_centavos'] ?? 0);
        $type = in_array((string) ($user['tipo_ingreso'] ?? ''), ['fijo', 'variable'], true)
            ? (string) $user['tipo_ingreso']
            : 'variable';

        echo View::render('income/nuevo', [
            'title' => __('income.nuevo.title'),
            'method' => $method,
            'period' => (new PeriodoService())->calcularPeriodo($user),
            'defaultAmount' => $defaultAmount,
            'type' => $type,
            'types' => (array) config('ingreso.types', []),
            'errors' => [],
        ], 'layouts/flow');
    }

    public function store(): void
    {
        $user = $this->user();
        $amount = parse_money_cents((string) input('monto', ''));
        $type = (string) input('tipo', 'variable');
        $errors = [];

        if ($amount === null || $amount <= 0) {
            $errors[] = __('income.errors.amount');
        }

        if (!in_array($type, (array) config('ingreso.types', []), true)) {
            $errors[] = __('income.errors.type');
        }

        if ($errors !== []) {
            $this->renderCreate($user, $errors, $amount ?? 0, $type);
            return;
        }

        try {
            $result = $this->service->crear($user, (int) $amount, $type, (string) input('moneda', ''));
        } catch (Throwable $exception) {
            Logger::warning('No se pudo crear el ingreso', [
                'user_id' => $user['id'],
                'error' => $exception->getMessage(),
            ]);

            $message = mb_strtolower($exception->getMessage());

            $this->renderCreate($user, [
                match (true) {
                    $exception->getMessage() === 'sin_tasa' => __('currency.errors.no_rate'),
                    str_contains($message, 'moneda') => __('currency.errors.invalid'),
                    default => __('income.errors.generic'),
                },
            ], (int) $amount, $type);
            return;
        }

        redirect('/ingresos/acreditar?id=' . $result['ingreso_id']);
    }

    public function acreditar(): void
    {
        $user = $this->user();
        $id = (int) ($_GET['id'] ?? 0);
        $this->renderAcreditar($user, $id);
    }

    public function finalizar(): void
    {
        $user = $this->user();
        $id = (int) ($_POST['ingreso_id'] ?? $_GET['id'] ?? 0);
        $recurrentes = $this->decisions($_POST['recurrentes'] ?? []);
        $gastosRecurrentes = $this->decisions($_POST['gastos_recurrentes'] ?? []);
        $abonos = ['deuda_categoria' => (string) input('deuda_categoria', '')];

        foreach (['deuda', 'meta', 'bolsa', 'deseo'] as $kind) {
            $raw = $_POST['abonos'][$kind] ?? [];
            $abonos[$kind] = [];

            foreach (is_array($raw) ? $raw : [] as $targetId => $value) {
                $cents = is_string($value) ? parse_money_cents($value) : null;

                if ($cents !== null && $cents > 0) {
                    $abonos[$kind][(int) $targetId] = $cents;
                }
            }
        }

        try {
            $result = $this->service->acreditarTodo($user, $id, $recurrentes, $gastosRecurrentes, $abonos);
        } catch (Throwable $exception) {
            Logger::warning('No se pudo acreditar el ingreso', [
                'user_id' => $user['id'],
                'ingreso_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            $this->renderAcreditar($user, $id, [__('income.errors.decisions')]);
            return;
        }

        if ($result['errores'] !== []) {
            Flash::warning('income.flash.saved_with_errors', [
                ':count' => (string) $result['abonos'],
                ':items' => implode(', ', $result['errores']),
            ]);
        } elseif ($result['abonos'] > 0) {
            Flash::success('income.flash.saved_with_payments', [':count' => (string) $result['abonos']]);
        } else {
            Flash::success('income.flash.saved');
        }

        redirect('/inicio');
    }

    public function show(): void
    {
        $user = $this->user();
        $this->renderShow($user, (int) ($_GET['id'] ?? 0));
    }

    public function update(): void
    {
        $user = $this->user();
        $id = (int) ($_POST['ingreso_id'] ?? 0);
        $amount = parse_money_cents((string) input('monto', ''));

        if ($amount === null || $amount <= 0) {
            $this->renderShow($user, $id, [__('income.errors.amount')]);
            return;
        }

        try {
            $this->service->actualizarMonto($user, $id, $amount, !empty($_POST['confirmar']));
        } catch (Throwable $exception) {
            if ($exception->getMessage() === 'income.errors.used_exceeds') {
                $this->renderShow($user, $id, [], ['action' => 'editar', 'monto' => (string) input('monto', '')]);
                return;
            }

            $this->renderShow($user, $id, [$this->incomeError($exception)]);
            return;
        }

        Flash::success('income.flash.updated');
        redirect('/ingresos/ver?id=' . $id);
    }

    public function destroy(): void
    {
        $user = $this->user();
        $id = (int) ($_POST['ingreso_id'] ?? 0);

        $withMovements = !empty($_POST['borrar_movimientos']);

        try {
            $deleted = $this->service->eliminar($user, $id, !empty($_POST['confirmar']), $withMovements);
        } catch (Throwable $exception) {
            if ($exception->getMessage() === 'income.errors.used_exceeds') {
                $this->renderShow($user, $id, [], ['action' => 'eliminar']);
                return;
            }

            Logger::warning('No se pudo eliminar el ingreso', [
                'user_id' => $user['id'],
                'ingreso_id' => $id,
                'error' => $exception->getMessage(),
            ]);
            $this->renderShow($user, $id, [$this->incomeError($exception)]);
            return;
        }

        if ($withMovements) {
            Flash::success('income.flash.deleted_with_movements', [':count' => (string) $deleted]);
        } else {
            Flash::success('income.flash.deleted');
        }
        redirect('/movimientos');
    }

    /**
     * @param list<string> $errors
     * @param array<string, string>|null $confirm
     */
    private function renderShow(array $user, int $id, array $errors = [], ?array $confirm = null): void
    {
        $ingreso = (new IngresoRepository())->findForUser($id, (int) $user['id']);

        if ($ingreso === null) {
            Flash::error('income.errors.not_found');
            redirect('/movimientos');
        }

        $editable = true;

        try {
            $this->service->ingresoEditable($user, $id);
        } catch (Throwable) {
            $editable = false;
        }

        $movements = (new \Cauce\Services\MovementReversalService())->countForPeriod(
            (int) $user['id'],
            (string) $ingreso['periodo_inicio'],
            (string) $ingreso['periodo_fin']
        );
        $incomesInPeriod = (new IngresoRepository())->countForPeriod(
            (int) $user['id'],
            (string) $ingreso['periodo_inicio'],
            (string) $ingreso['periodo_fin']
        );

        echo View::render('income/show', [
            'title' => __('income.show.title'),
            'movements' => $movements,
            'onlyIncome' => $incomesInPeriod <= 1,
            'ingreso' => $ingreso,
            'asignaciones' => (new AsignacionRepository())->forIngreso($id),
            'editable' => $editable,
            'errors' => $errors,
            'confirm' => $confirm,
        ], 'layouts/app');
    }

    private function incomeError(Throwable $exception): string
    {
        $message = $exception->getMessage();

        return match (true) {
            $message === 'income.errors.closed_period' => __('income.errors.closed_period'),
            $message === 'sin_tasa' => __('currency.errors.no_rate'),
            str_contains(mb_strtolower($message), 'no existe') => __('income.errors.not_found'),
            str_contains(mb_strtolower($message), 'monto') => __('income.errors.amount'),
            default => __('income.errors.generic'),
        };
    }

    /**
     * @param mixed $raw
     * @return array<int, string>
     */
    private function decisions(mixed $raw): array
    {
        $result = [];

        foreach (is_array($raw) ? $raw : [] as $id => $value) {
            if ((int) $id > 0 && in_array($value, ['aceptar', 'no_controlado'], true)) {
                $result[(int) $id] = (string) $value;
            }
        }

        return $result;
    }

    /**
     * @param list<string> $errors
     */
    private function renderCreate(array $user, array $errors, int $amountCents, string $type): void
    {
        $active = (new UsuarioMetodoRepository())->activeForUser((int) $user['id']);
        $method = $active !== null ? (new MetodoRepository())->findById((int) $active['metodo_id']) : null;

        if ($method === null) {
            Flash::error('income.errors.no_method');
            redirect('/ajustes/ingreso');
        }

        echo View::render('income/nuevo', [
            'title' => __('income.nuevo.title'),
            'method' => $method,
            'period' => (new PeriodoService())->calcularPeriodo($user),
            'defaultAmount' => $amountCents,
            'type' => $type,
            'types' => (array) config('ingreso.types', []),
            'errors' => $errors,
        ], 'layouts/flow');
    }

    /**
     * @param list<string> $errors
     * @param array<int|string, mixed> $old
     */
    private function renderAcreditar(array $user, int $ingresoId, array $errors = [], array $old = []): void
    {
        $ingreso = (new IngresoRepository())->findForUser($ingresoId, (int) $user['id']);

        if ($ingreso === null) {
            Flash::error('income.errors.not_found');
            redirect('/inicio');
        }

        $asignaciones = (new AsignacionRepository())->forIngreso($ingresoId);
        $pending = array_values(array_filter(
            $asignaciones,
            static fn (array $row): bool => ($row['estado'] ?? '') === 'pendiente_asignacion'
        ));

        if ($pending === [] && $errors === []) {
            Flash::info('income.flash.already_done');
            redirect('/inicio');
        }

        $method = $ingreso['metodo_id'] !== null
            ? (new MetodoRepository())->findById((int) $ingreso['metodo_id'])
            : null;
        $userId = (int) $user['id'];
        $types = [];

        foreach ((array) ($method['categorias'] ?? []) as $category) {
            $types[(string) $category['nombre']] = (string) $category['tipo'];
        }

        // Gastos recurrentes que se cobrarán en este período, y los que ya se
        // registraron como pendientes porque aún no había ingreso.
        $recurring = (new RecurringExpenseRepository())->dueInPeriod($userId, (string) $ingreso['periodo_fin']);
        $pendingRecurring = array_values(array_filter(
            (new GastoRepository())->pendingForUser($userId),
            static fn (array $row): bool => !empty($row['gasto_recurrente_id'])
                && (string) $row['periodo_inicio'] === (string) $ingreso['periodo_inicio']
        ));

        $savings = new SavingsRepository();
        $metas = array_values(array_filter(
            $savings->metasForUser($userId),
            static fn (array $row): bool => (int) $row['activa'] === 1 && (string) $row['tipo'] !== 'deseo'
        ));
        $bolsas = array_values(array_filter(
            $savings->accountsForUser($userId),
            static fn (array $row): bool => (int) $row['activa'] === 1
        ));
        $debts = array_values(array_filter(
            (new DebtRepository())->allForUser($userId, true),
            static fn (array $row): bool => (int) $row['saldo_actual_centavos'] > 0
        ));

        echo View::render('income/acreditar', [
            'title' => __('income.acreditar.title'),
            'ingreso' => $ingreso,
            'asignaciones' => $asignaciones,
            'categoryTypes' => $types,
            'method' => $method,
            'recurring' => $recurring,
            'pendingRecurring' => $pendingRecurring,
            'debts' => $debts,
            'debtCategories' => (new DebtService())->paymentCategories($user),
            'metas' => $metas,
            'bolsas' => $bolsas,
            'wishes' => (new WishlistRepository())->allForUser($userId, 'activo'),
            'errors' => $errors,
        ], 'layouts/flow');
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
