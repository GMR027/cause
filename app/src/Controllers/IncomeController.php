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
use Cauce\Services\AuthService;
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
        $decisiones = $_POST['decisiones'] ?? [];

        if (!is_array($decisiones)) {
            $decisiones = [];
        }

        try {
            $this->service->finalizar($user, $id, $decisiones);
        } catch (Throwable $exception) {
            Logger::warning('No se pudo finalizar el ingreso', [
                'user_id' => $user['id'],
                'ingreso_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            $this->renderAcreditar($user, $id, [__('income.errors.decisions')], $decisiones);
            return;
        }

        Flash::success('income.flash.saved');
        redirect('/inicio');
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

        echo View::render('income/acreditar', [
            'title' => __('income.acreditar.title'),
            'ingreso' => $ingreso,
            'asignaciones' => $asignaciones,
            'method' => $method,
            'reasons' => (array) config('ingreso.justification_reasons', []),
            'errors' => $errors,
            'old' => $old,
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
