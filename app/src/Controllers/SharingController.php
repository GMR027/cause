<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Controllers\Concerns\FinanceErrorTrait;
use Cauce\Helpers\Flash;
use Cauce\Helpers\View;
use Cauce\Services\AuthService;
use Cauce\Services\MoneyFxService;
use Cauce\Services\SharingService;
use Throwable;

/**
 * Sección «Compartir» del método (p. ej. el frasco Compartir de Cinco frascos).
 */
final class SharingController
{
    use FinanceErrorTrait;

    private SharingService $service;

    public function __construct()
    {
        $this->service = new SharingService();
    }

    public function index(): void
    {
        $user = $this->user();

        echo View::render('sharing/index', array_merge(
            $this->service->panel($user),
            ['title' => __('sharing.title')]
        ), 'layouts/app');
    }

    public function store(): void
    {
        $user = $this->user();
        $amount = parse_money_cents((string) input('monto', '')) ?? 0;

        try {
            $result = $this->service->register(
                $user,
                $amount,
                input('destinatario'),
                (string) input('fecha', 'now'),
                !empty($_POST['es_extra']),
                (string) input('moneda', '')
            );

            if (!empty($result['over_percentage'])) {
                Flash::warning('finance.flash.over_percentage');
            } else {
                Flash::success('sharing.flash.created');
            }

            MoneyFxService::queue($user, 'share', $amount, (string) input('moneda', ''));
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/compartir');
    }

    public function update(): void
    {
        $user = $this->user();
        $amount = parse_money_cents((string) input('monto', '')) ?? 0;

        try {
            $this->service->update(
                $user,
                (int) ($_POST['id'] ?? 0),
                $amount,
                input('destinatario'),
                !empty($_POST['es_extra']),
                (string) input('fecha', 'now')
            );
            Flash::success('sharing.flash.updated');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/compartir');
    }

    public function delete(): void
    {
        $user = $this->user();

        try {
            $this->service->delete($user, (int) ($_POST['id'] ?? 0));
            Flash::success('sharing.flash.deleted');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/compartir');
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
