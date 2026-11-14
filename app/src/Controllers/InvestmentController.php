<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Controllers\Concerns\FinanceErrorTrait;
use Cauce\Helpers\Flash;
use Cauce\Helpers\View;
use Cauce\Services\AuthService;
use Cauce\Services\InvestmentService;
use Throwable;

final class InvestmentController
{
    use FinanceErrorTrait;

    private InvestmentService $service;

    public function __construct()
    {
        $this->service = new InvestmentService();
    }

    public function index(): void
    {
        $user = $this->user();

        echo View::render('investment/index', array_merge(
            $this->service->panel($user),
            ['title' => __('investment.title')]
        ), 'layouts/app');
    }

    public function contribute(): void
    {
        $user = $this->user();
        $amount = parse_money_cents((string) input('monto', '')) ?? 0;

        try {
            $result = $this->service->contribute(
                $user,
                $amount,
                (string) input('instrumento', ''),
                (string) input('fecha', 'now'),
                !empty($_POST['es_extra']),
                (string) input('moneda', '')
            );
            Flash::success('investment.flash.contributed');

            if (!empty($result['over_percentage'])) {
                Flash::warning('finance.flash.over_percentage');
            }
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/inversion');
    }

    public function withdraw(): void
    {
        $user = $this->user();
        $amount = parse_money_cents((string) input('monto', '')) ?? 0;

        try {
            $result = $this->service->withdraw($user, $amount, (string) input('instrumento', ''), [
                'motivo' => (string) input('motivo', ''),
                'motivo_texto' => input('motivo_texto'),
                'fecha' => (string) input('fecha', 'now'),
                'destino_instrumento' => (string) input('destino_instrumento', ''),
            ], (string) input('moneda', ''));
            Flash::success('investment.flash.withdrawn');

            if (!empty($result['rebalanced'])) {
                Flash::info('investment.flash.rebalanced');
            }
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/inversion');
    }

    public function pause(): void
    {
        $user = $this->user();

        try {
            $this->service->pause($user, (string) input('motivo', ''), input('motivo_texto'));
            Flash::success('investment.flash.paused');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/inversion');
    }

    public function adjustPercentage(): void
    {
        $user = $this->user();
        $raw = trim((string) input('porcentaje', ''));

        if ($raw === '' || !is_numeric(str_replace(',', '.', $raw))) {
            Flash::error('finance.errors.percentage');
            redirect('/inversion');
        }

        $percent = (float) str_replace(',', '.', $raw);

        if ($percent < 0 || $percent > 100) {
            Flash::error('finance.errors.percentage');
            redirect('/inversion');
        }

        try {
            $this->service->adjustPercentage($user, $percent);
            Flash::success('investment.flash.percentage_updated');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/inversion');
    }

    public function storePosition(): void
    {
        $user = $this->user();

        try {
            $this->service->createPosition($user, $this->positionData());
            Flash::success('investment.flash.position_created');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/inversion');
    }

    public function updatePosition(): void
    {
        $user = $this->user();

        try {
            $this->service->updatePosition($user, (int) ($_POST['id'] ?? 0), $this->positionData());
            Flash::success('investment.flash.position_updated');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/inversion');
    }

    public function updatePositionValue(): void
    {
        $user = $this->user();
        $value = parse_money_cents((string) input('valor', '')) ?? 0;

        try {
            $this->service->updatePositionValue(
                $user,
                (int) ($_POST['id'] ?? 0),
                $value,
                (string) input('fecha', gmdate('Y-m-d'))
            );
            Flash::success('investment.flash.position_value_updated');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/inversion');
    }

    public function deletePosition(): void
    {
        $user = $this->user();

        try {
            $this->service->deletePosition($user, (int) ($_POST['id'] ?? 0));
            Flash::success('investment.flash.position_deleted');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/inversion');
    }

    public function refreshQuotes(): void
    {
        $user = $this->user();
        $last = (int) ($_SESSION['_quotes_refresh_at'] ?? 0);

        if ($last > 0 && time() - $last < 60) {
            Flash::warning('investment.flash.quotes_throttled');
            redirect('/inversion');
        }

        try {
            $result = $this->service->refreshQuotes($user);
            $_SESSION['_quotes_refresh_at'] = time();
            Flash::success('investment.flash.quotes_updated');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/inversion');
    }

    /**
     * @return array<string, mixed>
     */
    private function positionData(): array
    {
        $quantity = str_replace(',', '.', trim((string) input('cantidad', '0')));

        return [
            'tipo' => (string) input('tipo', 'otro'),
            'mercado' => (string) input('mercado', 'OTRO'),
            'ticker' => (string) input('ticker', ''),
            'simbolo_proveedor' => (string) input('simbolo_proveedor', ''),
            'nombre' => (string) input('nombre', ''),
            'moneda' => (string) input('moneda', ''),
            'cantidad' => is_numeric($quantity) ? (float) $quantity : 0.0,
            'costo_total_centavos' => parse_money_cents((string) input('costo_total', '')) ?? 0,
            'modo_valor' => (string) input('modo_valor', 'manual'),
            'valor_manual_centavos' => parse_money_cents((string) input('valor_manual', '')) ?? 0,
            'fecha_valor' => (string) input('fecha_valor', ''),
        ];
    }

    public function updateContribution(): void
    {
        $user = $this->user();
        $amount = parse_money_cents((string) input('monto', '')) ?? 0;

        try {
            $this->service->updateContribution(
                $user,
                (int) ($_POST['id'] ?? 0),
                $amount,
                !empty($_POST['es_extra']),
                (string) input('fecha', 'now')
            );
            Flash::success('investment.flash.movement_updated');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/inversion');
    }

    public function deleteContribution(): void
    {
        $user = $this->user();

        try {
            $this->service->deleteContribution($user, (int) ($_POST['id'] ?? 0));
            Flash::success('investment.flash.movement_deleted');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/inversion');
    }

    public function updateWithdrawal(): void
    {
        $user = $this->user();
        $amount = parse_money_cents((string) input('monto', '')) ?? 0;

        try {
            $this->service->updateWithdrawal($user, (int) ($_POST['id'] ?? 0), $amount, [
                'motivo' => (string) input('motivo', ''),
                'motivo_texto' => input('motivo_texto'),
                'fecha' => (string) input('fecha', 'now'),
            ]);
            Flash::success('investment.flash.movement_updated');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/inversion');
    }

    public function deleteWithdrawal(): void
    {
        $user = $this->user();

        try {
            $this->service->deleteWithdrawal($user, (int) ($_POST['id'] ?? 0));
            Flash::success('investment.flash.movement_deleted');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/inversion');
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
