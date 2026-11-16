<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\Logger;
use Cauce\Helpers\View;
use Cauce\Services\AuthService;
use Cauce\Services\FrequentExpensesService;
use Cauce\Services\NetWorthService;
use Cauce\Services\ScoreService;
use Cauce\Services\SubscriptionService;
use Throwable;

/**
 * Reportes: patrimonio neto y suscripciones.
 */
final class ReportController
{
    public function index(): void
    {
        $user = $this->user();
        $netWorth = new NetWorthService();
        $range = (string) (input('rango', '6m') ?? '6m');

        if (!array_key_exists($range, NetWorthService::RANGES)) {
            $range = '6m';
        }

        echo View::render('reports/index', [
            'title' => __('reports.title'),
            'netWorth' => $netWorth->summary($user),
            'series' => $netWorth->series($user, $range),
            'range' => $range,
            'ranges' => array_keys(NetWorthService::RANGES),
            'subscriptions' => (new SubscriptionService())->panel($user),
            'frequent' => (new FrequentExpensesService())->top($user),
            'score' => (new ScoreService())->panel($user, true),
        ], 'layouts/app');
    }

    public function subscriptions(): void
    {
        $user = $this->user();

        $timezone = (new \Cauce\Services\PeriodoService())->userTimezone($user);

        echo View::render('reports/subscriptions', array_merge(
            (new SubscriptionService())->panel($user),
            [
                'title' => __('reports.subscriptions.title'),
                'categories' => (new \Cauce\Services\GastoService())->categoriasGasto($user),
                'today' => (new \DateTimeImmutable('now', $timezone))->format('Y-m-d'),
            ]
        ), 'layouts/app');
    }

    public function updateCash(): void
    {
        $user = $this->user();
        $raw = trim((string) input('efectivo', ''));

        try {
            $cents = $raw === '' ? null : parse_money_cents($raw);

            if ($raw !== '' && $cents === null) {
                throw new \InvalidArgumentException('Monto inválido.');
            }

            (new NetWorthService())->updateCash($user, $cents);
            AuthService::refresh();
            Flash::success('reports.cash.saved');
        } catch (Throwable $exception) {
            Logger::warning('No se pudo actualizar el efectivo manual', [
                'user_id' => $user['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
            Flash::error('reports.cash.error');
        }

        redirect('/reportes');
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
