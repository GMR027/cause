<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Controllers\Concerns\FinanceErrorTrait;
use Cauce\Helpers\Flash;
use Cauce\Helpers\View;
use Cauce\Services\AuthService;
use Cauce\Services\DonationService;
use Throwable;

final class DonationController
{
    use FinanceErrorTrait;

    private DonationService $service;

    public function __construct()
    {
        $this->service = new DonationService();
    }

    public function index(): void
    {
        $user = $this->user();

        echo View::render('donation/index', array_merge(
            $this->service->panel($user),
            ['title' => __('donation.title')]
        ), 'layouts/app');
    }

    public function donate(): void
    {
        $user = $this->user();
        $amount = parse_money_cents((string) input('monto', '')) ?? 0;

        try {
            $result = $this->service->donate(
                $user,
                $amount,
                input('causa'),
                (string) input('fecha', 'now'),
                !empty($_POST['es_extra']),
                (string) input('moneda', '')
            );
            Flash::success('donation.flash.donated');

            if (!empty($result['over_percentage'])) {
                Flash::warning('finance.flash.over_percentage');
            }
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/donacion');
    }

    public function activate(): void
    {
        $user = $this->user();
        $raw = trim((string) input('porcentaje', ''));

        if ($raw === '' || !is_numeric(str_replace(',', '.', $raw))) {
            Flash::error('donation.errors.invalid_percent');
            redirect('/donacion');
        }

        $percent = (float) str_replace(',', '.', $raw);

        if ($percent < 0 || $percent > 100) {
            Flash::error('finance.errors.percentage');
            redirect('/donacion');
        }

        try {
            $this->service->activate($user, $percent);
            Flash::success($percent > 0 ? 'donation.flash.activated' : 'donation.flash.deactivated');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/donacion');
    }

    public function updateDonation(): void
    {
        $user = $this->user();
        $amount = parse_money_cents((string) input('monto', '')) ?? 0;

        try {
            $this->service->updateDonation(
                $user,
                (int) ($_POST['id'] ?? 0),
                $amount,
                input('causa'),
                !empty($_POST['es_extra']),
                (string) input('fecha', 'now')
            );
            Flash::success('donation.flash.updated');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/donacion');
    }

    public function deleteDonation(): void
    {
        $user = $this->user();

        try {
            $this->service->deleteDonation($user, (int) ($_POST['id'] ?? 0));
            Flash::success('donation.flash.deleted');
        } catch (Throwable $exception) {
            $this->flashFinanceError($exception);
        }

        redirect('/donacion');
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
