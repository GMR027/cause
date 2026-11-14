<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
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
            Flash::error('moves.flash.error', [':message' => $exception->getMessage()]);
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
            Flash::error('moves.flash.error', [':message' => $exception->getMessage()]);
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
}
