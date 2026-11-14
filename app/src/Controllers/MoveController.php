<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\Logger;
use Cauce\Helpers\View;
use Cauce\Services\AuthService;
use Cauce\Services\MoveService;
use Throwable;

/**
 * Movimientos entre categorías y detalle de categoría.
 */
final class MoveController
{
    private MoveService $service;

    public function __construct()
    {
        $this->service = new MoveService();
    }

    public function index(): void
    {
        $user = $this->user();

        echo View::render('moves/index', array_merge(
            $this->service->panel($user),
            ['title' => __('moves.title')]
        ), 'layouts/app');
    }

    public function store(): void
    {
        $user = $this->user();
        $amount = parse_money_cents((string) input('monto', '')) ?? 0;

        try {
            $result = $this->service->move(
                $user,
                (string) input('origen', ''),
                (string) input('destino', ''),
                $amount,
                (string) input('motivo', ''),
                input('motivo_texto'),
                (string) input('fecha', 'now')
            );

            Flash::success('moves.flash.created');

            if (!empty($result['warning'])) {
                Flash::warning('moves.flash.large_move');
            }
        } catch (Throwable $exception) {
            Flash::error('moves.flash.error', [':message' => $this->moveErrorReason($exception)]);
        }

        redirect('/mover');
    }

    public function category(): void
    {
        $user = $this->user();
        $part = (string) input('parte', '');

        try {
            $data = $this->service->category($user, $part);
        } catch (Throwable $exception) {
            Flash::error('moves.flash.error', [':message' => $this->moveErrorReason($exception)]);
            redirect('/mover');
        }

        echo View::render('moves/category', array_merge($data, [
            'title' => $part,
        ]), 'layouts/app');
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

    private function moveErrorReason(Throwable $exception): string
    {
        $key = match ($exception->getMessage()) {
            'La categoría no existe en tu método.' => 'moves.errors.category',
            'El monto debe ser mayor que cero.' => 'moves.errors.amount',
            'El origen y el destino deben ser distintos.' => 'moves.errors.same',
            'Motivo de movimiento inválido.' => 'moves.errors.reason',
            'No puedes mover más que el disponible del origen.' => 'moves.errors.exceeds',
            'Solo puedes registrar movimientos en el período actual.' => 'moves.errors.closed',
            'Solo puedes mover montos en el período actual.' => 'moves.errors.closed',
            'sin_tasa' => 'currency.errors.no_rate',
            default => null,
        };

        if ($key === null) {
            Logger::warning('No se pudo registrar el movimiento entre categorías', ['error' => $exception->getMessage()]);
            $key = 'moves.errors.generic';
        }

        return __($key);
    }
}
