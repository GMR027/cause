<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\Logger;
use Cauce\Helpers\View;
use Cauce\Services\MoneyFxService;
use Cauce\Services\AuthService;
use Cauce\Services\DebtService;
use Throwable;

/**
 * Panel de deudas, pagos, estrategia y recordatorios.
 */
final class DebtController
{
    private DebtService $service;

    public function __construct()
    {
        $this->service = new DebtService();
    }

    public function index(): void
    {
        $user = $this->user();

        echo View::render('debts/index', array_merge(
            $this->service->panel($user),
            ['title' => __('debts.title')]
        ), 'layouts/app');
    }

    public function store(): void
    {
        $user = $this->user();

        try {
            $this->service->create($user, $this->dataFromPost());
            Flash::success('debts.flash.created');
        } catch (Throwable $exception) {
            $this->flashError($exception);
        }

        redirect('/deudas');
    }

    public function update(): void
    {
        $user = $this->user();

        try {
            $this->service->update($user, (int) input('id', '0'), $this->dataFromPost());
            Flash::success('debts.flash.updated');
        } catch (Throwable $exception) {
            $this->flashError($exception);
        }

        redirect('/deudas');
    }

    public function delete(): void
    {
        $user = $this->user();

        try {
            $this->service->delete($user, (int) input('id', '0'));
            Flash::success('debts.flash.deleted');
        } catch (Throwable $exception) {
            $this->flashError($exception);
        }

        redirect('/deudas');
    }

    public function strategy(): void
    {
        $user = $this->user();

        try {
            $this->service->setStrategy($user, (string) input('estrategia', 'manual'));
            Flash::success('debts.flash.strategy_updated');
        } catch (Throwable $exception) {
            $this->flashError($exception);
        }

        redirect('/deudas');
    }

    public function pay(): void
    {
        $user = $this->user();

        try {
            $result = $this->service->pay(
                $user,
                (int) input('deuda_id', '0'),
                parse_money_cents((string) input('monto', '')) ?? 0,
                (string) input('fecha', gmdate('Y-m-d')),
                input('nota'),
                !empty($_POST['registrar_gasto']),
                input('parte_metodo')
            );
            Flash::success(!empty($result['settled']) ? 'debts.flash.paid_off' : 'debts.flash.payment_saved');

            // Al liquidarla no va el cerdito roto: la festeja DebtPaidFxService.
            if (empty($result['settled'])) {
                MoneyFxService::queue(
                    $user,
                    'out',
                    (int) ($result['amount_centavos'] ?? 0),
                    (string) ($result['debt']['moneda'] ?? '')
                );
            }

            if (!empty($result['expense']['excedido'])) {
                Flash::warning('debts.flash.payment_excess');
            }
        } catch (Throwable $exception) {
            Logger::warning('No se pudo registrar el pago de deuda', [
                'user_id' => $user['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
            $this->flashError($exception);
        }

        redirect('/deudas');
    }

    public function reminder(): void
    {
        $user = $this->user();

        try {
            $this->service->createReminder(
                $user,
                (int) input('deuda_id', '0'),
                parse_money_cents((string) input('monto', '')) ?? 0,
                (string) input('recurrencia', 'mensual'),
                (string) input('proxima_ejecucion', gmdate('Y-m-d')),
                (string) input('parte_metodo', '')
            );
            Flash::success('debts.flash.reminder_saved');
        } catch (Throwable $exception) {
            $this->flashError($exception);
        }

        redirect('/deudas');
    }

    /**
     * @return array<string, mixed>
     */
    private function dataFromPost(): array
    {
        return [
            'nombre' => (string) input('nombre', ''),
            'moneda' => (string) input('moneda', ''),
            'monto_total_centavos' => parse_money_cents((string) input('monto_total', '')) ?? 0,
            'saldo_actual_centavos' => parse_money_cents((string) input('saldo_actual', '')) ?? 0,
            'tasa_interes' => input('tasa_interes'),
            'pago_minimo_centavos' => parse_money_cents((string) input('pago_minimo', '')),
            'fecha_limite' => (string) input('fecha_limite', ''),
        ];
    }

    private function flashError(Throwable $exception): void
    {
        $message = $exception->getMessage();

        $key = match (true) {
            $message === 'sin_tasa' => 'currency.errors.no_rate',
            $message === 'sin_ingreso' => 'debts.errors.no_income',
            $message === 'fuera_periodo' => 'debts.errors.out_of_period',
            str_contains($message, 'fecha no es válida') => 'debts.errors.date',
            str_contains($message, 'no existe') => 'debts.errors.not_found',
            str_contains($message, 'liquidada') => 'debts.errors.closed',
            str_contains($message, 'superar el saldo') => 'debts.errors.overpay',
            str_contains($message, 'categoría') => 'debts.errors.category',
            str_contains($message, 'saldo actual') => 'debts.errors.balance',
            str_contains($message, 'monto total') => 'debts.errors.total',
            str_contains($message, 'tasa') => 'debts.errors.rate',
            str_contains($message, 'nombre') => 'debts.errors.name',
            str_contains($message, 'recordatorio') => 'debts.errors.reminder',
            str_contains($message, 'Recurrencia') => 'debts.errors.recurrence',
            str_contains($message, 'próxima fecha') => 'debts.errors.recurring_date_past',
            str_contains($message, 'Estrategia') => 'debts.errors.strategy',
            str_contains($message, 'pago') => 'debts.errors.amount',
            default => 'debts.errors.generic',
        };

        Flash::error($key);
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
