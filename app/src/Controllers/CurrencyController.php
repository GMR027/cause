<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\Logger;
use Cauce\Helpers\View;
use Cauce\Repositories\UserRepository;
use Cauce\Services\AuthService;
use Cauce\Services\CurrencyRecalculationService;
use Cauce\Services\CurrencyService;
use Cauce\Services\ExchangeRateService;
use Throwable;

/**
 * Monedas del usuario y selección de la principal.
 */
final class CurrencyController
{
    public function index(): void
    {
        $user = $this->user();
        $service = new CurrencyService();

        echo View::render('ajustes/monedas', [
            'title' => __('currency.title'),
            'currencies' => $service->currencies($user),
            'available' => $service->available($user),
            'principal' => $service->principal($user),
            'rates' => (new ExchangeRateService())->all(),
        ], 'layouts/app');
    }

    public function add(): void
    {
        $user = $this->user();

        try {
            (new CurrencyService())->add($user, (string) input('codigo', ''));
            Flash::success('currency.flash.added');
        } catch (Throwable $exception) {
            Flash::error('currency.errors.add', [':message' => $exception->getMessage()]);
        }

        redirect('/ajustes/monedas');
    }

    public function remove(): void
    {
        $user = $this->user();

        try {
            (new CurrencyService())->remove($user, (string) input('codigo', ''));
            Flash::success('currency.flash.removed');
        } catch (Throwable $exception) {
            Flash::error('currency.errors.remove', [':message' => $exception->getMessage()]);
        }

        redirect('/ajustes/monedas');
    }

    public function principal(): void
    {
        $user = $this->user();

        try {
            $old = (new CurrencyService())->setPrincipal($user, (string) input('codigo', ''));
            $fresh = (new UserRepository())->findById((int) $user['id']);

            if ($fresh !== null) {
                try {
                    (new CurrencyRecalculationService())->forUser($fresh, $old);
                } catch (Throwable $exception) {
                    Logger::warning('No se pudieron recalcular las monedas', [
                        'user_id' => $user['id'] ?? null,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            AuthService::refresh();
            Flash::success('currency.flash.principal');
        } catch (Throwable $exception) {
            Flash::error('currency.errors.principal', [':message' => $exception->getMessage()]);
        }

        redirect('/ajustes/monedas');
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
