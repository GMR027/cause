<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Controllers\Concerns\FinanceErrorTrait;
use Cauce\Helpers\Flash;
use Cauce\Helpers\View;
use Cauce\Services\MoneyFxService;
use Cauce\Services\AuthService;
use Cauce\Services\SavingsService;
use Throwable;

final class SavingsController
{
    use FinanceErrorTrait;

    private SavingsService $service;

    public function __construct()
    {
        $this->service = new SavingsService();
    }

    public function index(): void
    {
        $user = $this->user();

        echo View::render('savings/index', array_merge(
            $this->service->panel($user),
            ['title' => __('savings.title')]
        ), 'layouts/app');
    }

    public function storeMeta(): void
    {
        $user = $this->user();

        try {
            $this->service->createMeta($user, $this->metaData());
            Flash::success('savings.flash.meta_created');
            \Cauce\Services\MoneyFxService::queue($user, 'new', 0, null, 'new_meta');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/ahorro');
    }

    public function updateMeta(): void
    {
        $user = $this->user();

        try {
            $this->service->updateMeta($user, (int) ($_POST['id'] ?? 0), $this->metaData());
            Flash::success('savings.flash.meta_updated');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/ahorro');
    }

    public function deactivateMeta(): void
    {
        $user = $this->user();

        try {
            $this->service->deactivateMeta($user, (int) ($_POST['id'] ?? 0));
            Flash::success('savings.flash.meta_deactivated');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/ahorro');
    }

    public function contribute(): void
    {
        $user = $this->user();
        $metaId = $this->metaId($_POST['meta_id'] ?? null);
        $accountId = (int) ($_POST['cuenta_id'] ?? 0);
        $amount = parse_money_cents((string) input('monto', '')) ?? 0;

        try {
            $result = $this->service->contribute(
                $user,
                $accountId,
                $metaId,
                $amount,
                (string) input('fecha', 'now'),
                !empty($_POST['ya_ahorrado'])
            );
            Flash::success('savings.flash.contributed');
            MoneyFxService::queue($user, 'save', $amount, (string) ($result['currency'] ?? ''));

            if (!empty($result['over_percentage'])) {
                Flash::warning('finance.flash.over_percentage');
            }
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/ahorro');
    }

    public function withdraw(): void
    {
        $user = $this->user();
        $metaId = $this->metaId($_POST['meta_id'] ?? null);
        $accountId = (int) ($_POST['cuenta_id'] ?? 0);
        $amount = parse_money_cents((string) input('monto', '')) ?? 0;

        try {
            $currency = $this->service->withdraw(
                $user,
                $accountId,
                $metaId,
                $amount,
                (string) input('motivo', ''),
                input('motivo_texto'),
                (string) input('fecha', 'now')
            );
            Flash::success('savings.flash.withdrawn');
            MoneyFxService::queue($user, 'out', $amount, $currency);
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/ahorro');
    }

    public function storeAccount(): void
    {
        $user = $this->user();

        try {
            $this->service->createAccount($user, $this->accountData());
            Flash::success('savings.flash.account_created');
            \Cauce\Services\MoneyFxService::queue($user, 'new', 0, null, 'new_bag');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/ahorro');
    }

    public function updateAccount(): void
    {
        $user = $this->user();

        try {
            $this->service->updateAccount($user, (int) ($_POST['id'] ?? 0), $this->accountData());
            Flash::success('savings.flash.account_updated');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/ahorro');
    }

    public function archiveAccount(): void
    {
        $user = $this->user();

        try {
            $this->service->archiveAccount($user, (int) ($_POST['id'] ?? 0));
            Flash::success('savings.flash.account_archived');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/ahorro');
    }

    public function transfer(): void
    {
        $user = $this->user();
        $amount = parse_money_cents((string) input('monto', '')) ?? 0;

        try {
            $this->service->transfer(
                $user,
                (int) ($_POST['cuenta_origen_id'] ?? 0),
                (int) ($_POST['cuenta_destino_id'] ?? 0),
                $amount,
                (string) input('fecha', 'now'),
                input('nota')
            );
            Flash::success('savings.flash.transferred');
            \Cauce\Services\MoneyFxService::queue($user, 'transfer', $amount);
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/ahorro');
    }

    public function assignMeta(): void
    {
        $user = $this->user();
        $amount = parse_money_cents((string) input('monto', '')) ?? 0;

        try {
            $this->service->assignToMeta(
                $user,
                (int) ($_POST['cuenta_id'] ?? 0),
                (int) ($_POST['meta_id'] ?? 0),
                $amount,
                (string) input('fecha', 'now')
            );
            Flash::success('savings.flash.assigned');
            \Cauce\Services\MoneyFxService::queue($user, 'transfer', $amount, null, 'transfer_meta');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/ahorro');
    }

    public function moveMeta(): void
    {
        $user = $this->user();

        try {
            $this->service->moveMeta(
                $user,
                (int) ($_POST['meta_id'] ?? 0),
                (int) ($_POST['cuenta_destino_id'] ?? 0),
                (string) input('fecha', 'now')
            );
            Flash::success('savings.flash.meta_moved');
            \Cauce\Services\MoneyFxService::queue($user, 'new', 0, null, 'new_meta_moved');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/ahorro');
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
                (string) input('fecha', 'now'),
                !empty($_POST['ya_ahorrado'])
            );
            Flash::success('savings.flash.movement_updated');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/ahorro');
    }

    public function deleteContribution(): void
    {
        $user = $this->user();

        try {
            $this->service->deleteContribution($user, (int) ($_POST['id'] ?? 0));
            Flash::success('savings.flash.movement_deleted');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/ahorro');
    }

    public function updateWithdrawal(): void
    {
        $user = $this->user();
        $amount = parse_money_cents((string) input('monto', '')) ?? 0;

        try {
            $this->service->updateWithdrawal(
                $user,
                (int) ($_POST['id'] ?? 0),
                $amount,
                (string) input('motivo', ''),
                input('motivo_texto'),
                (string) input('fecha', 'now')
            );
            Flash::success('savings.flash.movement_updated');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/ahorro');
    }

    public function deleteWithdrawal(): void
    {
        $user = $this->user();

        try {
            $this->service->deleteWithdrawal($user, (int) ($_POST['id'] ?? 0));
            Flash::success('savings.flash.movement_deleted');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/ahorro');
    }

    public function updateEmergency(): void
    {
        $user = $this->user();

        try {
            $this->service->updateEmergency($user, (int) ($_POST['meta_id'] ?? 0));
            Flash::success('savings.flash.emergency_updated');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/ahorro');
    }

    /**
     * @return array<string, mixed>
     */
    private function metaData(): array
    {
        return [
            'nombre' => (string) input('nombre', ''),
            'tipo' => (string) input('tipo', 'ahorro'),
            'objetivo_centavos' => parse_money_cents((string) input('objetivo', '')) ?? 0,
            'cuenta_id' => (int) ($_POST['cuenta_id'] ?? 0),
            'fecha_objetivo' => (string) input('fecha_objetivo', ''),
            'plazo_meses' => input('plazo_meses', ''),
            'activa' => !empty($_POST['activa']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accountData(): array
    {
        return [
            'nombre' => (string) input('nombre', ''),
            'tipo' => (string) input('tipo', 'otro'),
            'institucion' => (string) input('institucion', ''),
            'moneda' => (string) input('moneda', ''),
            'saldo_inicial_centavos' => parse_money_cents((string) input('saldo_inicial', '')) ?? 0,
            'activa' => !empty($_POST['activa']),
            'predeterminada' => !empty($_POST['predeterminada']),
        ];
    }

    private function metaId(mixed $raw): ?int
    {
        return $raw === null || $raw === '' || $raw === 'bag' ? null : (int) $raw;
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
