<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\Logger;
use Cauce\Helpers\View;
use Cauce\Services\AdminAuditService;
use Cauce\Services\CurrencyRecalculationService;
use Cauce\Services\CurrencyService;
use Cauce\Services\ExchangeRateService;
use Throwable;

/**
 * Administración global de tasas de cambio.
 */
final class AdminCurrencyController
{
    public function index(): void
    {
        $service = new ExchangeRateService();
        $editId = (int) input('editar', '0');

        echo View::render('admin/rates', [
            'title' => __('currency.admin.title'),
            'rates' => $service->all(),
            'editing' => $editId > 0 ? $service->find($editId) : null,
            'currencies' => CurrencyService::SUPPORTED,
        ], 'layouts/app');
    }

    public function save(): void
    {
        $service = new ExchangeRateService();
        $id = (int) input('id', '0');

        try {
            $saved = $service->save([
                'moneda_origen' => (string) input('moneda_origen', ''),
                'moneda_destino' => (string) input('moneda_destino', ''),
                'tasa' => (string) input('tasa', ''),
                'fecha' => (string) input('fecha', ''),
            ], $id > 0 ? $id : null);
            AdminAuditService::log('admin.rate.save', 'tasas_cambio', $saved, [
                'origen' => (string) input('moneda_origen', ''),
                'destino' => (string) input('moneda_destino', ''),
                'fecha' => (string) input('fecha', ''),
            ]);
            $this->recalculateAll();
            Flash::success('currency.admin.flash.saved');
        } catch (Throwable $exception) {
            Flash::error(
                $exception instanceof \PDOException
                    ? 'currency.admin.errors.save_generic'
                    : 'currency.admin.errors.save',
                $exception instanceof \PDOException ? [] : [':message' => $exception->getMessage()]
            );
        }

        redirect('/admin/monedas');
    }

    public function delete(): void
    {
        $id = (int) input('id', '0');

        try {
            (new ExchangeRateService())->delete($id);
            AdminAuditService::log('admin.rate.delete', 'tasas_cambio', $id);
            $this->recalculateAll();
            Flash::success('currency.admin.flash.deleted');
        } catch (Throwable $exception) {
            Flash::error('currency.admin.errors.delete', [':message' => $exception->getMessage()]);
        }

        redirect('/admin/monedas');
    }

    public function recalculate(): void
    {
        try {
            (new CurrencyRecalculationService())->forAll();
            AdminAuditService::log('admin.rate.recalculate', 'tasas_cambio');
            Flash::success('currency.admin.flash.recalculated');
        } catch (Throwable $exception) {
            Flash::error('currency.admin.errors.recalculate', [':message' => $exception->getMessage()]);
        }

        redirect('/admin/monedas');
    }

    private function recalculateAll(): void
    {
        try {
            (new CurrencyRecalculationService())->forAll();
        } catch (Throwable $exception) {
            Logger::warning('No se pudieron recalcular las conversiones', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
