<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Controllers\Concerns\FinanceErrorTrait;
use Cauce\Helpers\Flash;
use Cauce\Helpers\View;
use Cauce\Services\MoneyFxService;
use Cauce\Services\AuthService;
use Cauce\Services\WishlistService;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Lista de deseos: crear, editar, comprar y descartar.
 */
final class WishlistController
{
    use FinanceErrorTrait;

    private WishlistService $service;

    public function __construct()
    {
        $this->service = new WishlistService();
    }

    public function index(): void
    {
        $user = $this->user();
        $state = isset($_GET['estado']) && is_string($_GET['estado']) ? $_GET['estado'] : 'activo';

        echo View::render('wishlist/index', array_merge(
            $this->service->panel($user, $state),
            ['title' => __('wishlist.title')]
        ), 'layouts/app');
    }

    public function store(): void
    {
        $user = $this->user();

        try {
            $data = $this->dataFromPost();
            $this->service->create($user, $data);
            Flash::success('wishlist.flash.created');
            MoneyFxService::queue(
                $user,
                'wish',
                (int) ($data['monto_estimado_centavos'] ?? 0),
                (string) ($data['moneda'] ?? '')
            );
        } catch (Throwable $exception) {
            $this->flashError($exception);
        }

        redirect('/deseos');
    }

    public function update(): void
    {
        $user = $this->user();
        $id = (int) input('id', '0');

        try {
            $this->service->update($user, $id, $this->dataFromPost());
            Flash::success('wishlist.flash.updated');
        } catch (Throwable $exception) {
            $this->flashError($exception);
        }

        redirect('/deseos');
    }

    public function purchase(): void
    {
        $user = $this->user();
        $id = (int) input('id', '0');
        $amount = parse_money_cents((string) input('monto', '')) ?? 0;

        try {
            $result = $this->service->purchase($user, $id, [
                'monto_centavos' => $amount,
                'fecha' => (string) input('fecha', $this->today($user)),
                'parte_metodo' => (string) input('parte_metodo', ''),
                'register_expense' => !empty($_POST['registrar_gasto']),
                'skip_cooling' => !empty($_POST['saltar_enfriamiento']),
                'use_savings' => !empty($_POST['usar_ahorro']),
            ]);

            Flash::success('wishlist.flash.purchased');

            if (!empty($result['gasto_id']) || (int) ($result['used_savings_centavos'] ?? 0) > 0) {
                MoneyFxService::queue(
                    $user,
                    'out',
                    (int) ($result['amount_centavos'] ?? 0),
                    (string) ($result['item']['moneda'] ?? '')
                );
            }

            if (!empty($result['savings_failed'])) {
                Flash::warning('wishlist.flash.savings_failed');
            } elseif ((int) ($result['used_savings_centavos'] ?? 0) > 0) {
                Flash::info('wishlist.flash.savings_used', [
                    ':amount' => format_money((int) $result['used_savings_centavos'], (string) ($user['moneda_activa'] ?? 'MXN')),
                ]);
            }

            if (($result['expense']['estado'] ?? '') === 'pendiente_asignacion') {
                Flash::warning('wishlist.flash.pending');
            } elseif (!empty($result['expense']['excedido'])) {
                Flash::warning('wishlist.flash.excess');
            }
        } catch (Throwable $exception) {
            $this->flashError($exception);
        }

        redirect('/deseos');
    }

    public function contribute(): void
    {
        $user = $this->user();
        $id = (int) input('id', '0');
        $amount = parse_money_cents((string) input('monto', '')) ?? 0;

        try {
            $result = $this->service->contribute($user, $id, $amount, (string) input('fecha', 'now'));
            Flash::success($result['created'] ? 'wishlist.flash.contributed_first' : 'wishlist.flash.contributed');
            MoneyFxService::queue($user, 'wish', $amount, (string) ($result['status']['currency'] ?? ''));

            if (!empty($result['status']['over_percentage'])) {
                Flash::warning('finance.flash.over_percentage');
            }
        } catch (Throwable $exception) {
            $message = $exception->getMessage();

            if (in_array($message, ['El deseo no existe.', 'Ese deseo ya está cerrado.'], true)) {
                $this->flashError($exception);
            } else {
                $this->flashFinanceError($exception);
            }
        }

        redirect('/deseos');
    }

    public function discard(): void
    {
        $user = $this->user();
        $id = (int) input('id', '0');

        try {
            $this->service->discard($user, $id);
            Flash::success('wishlist.flash.discarded');
        } catch (Throwable $exception) {
            $this->flashError($exception);
        }

        redirect('/deseos');
    }

    /**
     * @return array<string, mixed>
     */
    private function dataFromPost(): array
    {
        return [
            'nombre' => (string) input('nombre', ''),
            'moneda' => (string) input('moneda', ''),
            'monto_estimado_centavos' => parse_money_cents((string) input('monto', '')) ?? 0,
            'prioridad' => (int) input('prioridad', '2'),
            'fecha_objetivo' => (string) input('fecha_objetivo', ''),
            'fecha_enfriamiento' => (string) input('fecha_enfriamiento', ''),
            'dias_enfriamiento' => (int) input('dias_enfriamiento', '0'),
            'nota' => (string) input('nota', ''),
        ];
    }

    private function flashError(Throwable $exception): void
    {
        $message = $exception->getMessage();

        $key = match (true) {
            $message === 'cooling' => 'wishlist.errors.cooling',
            $message === 'El deseo no existe.' => 'wishlist.errors.not_found',
            $message === 'Ese deseo ya está cerrado.' => 'wishlist.errors.closed',
            $message === 'Elige una categoría para el gasto.' => 'wishlist.errors.no_category',
            $message === 'sin_ingreso' => 'wishlist.errors.no_income',
            $message === 'fuera_periodo' => 'wishlist.errors.out_of_period',
            $message === 'El deseo necesita un nombre.' => 'wishlist.errors.name',
            $message === 'El monto debe ser mayor que cero.' => 'wishlist.errors.amount',
            $message === 'La fecha objetivo no es válida.' => 'wishlist.errors.date',
            $message === 'Moneda no válida.' => 'currency.errors.invalid',
            default => 'wishlist.errors.generic',
        };

        Flash::error($key);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function today(array $user): string
    {
        try {
            $timezone = new DateTimeZone((string) ($user['zona_horaria'] ?? config('app.timezone', 'UTC')));
        } catch (\Exception) {
            $timezone = new DateTimeZone('UTC');
        }

        return (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
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
