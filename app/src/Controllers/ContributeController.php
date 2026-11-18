<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\View;
use Cauce\Services\AuthService;

/**
 * Acceso rápido "Aportar / Retirar" del FAB: elige panel y abre su hoja.
 */
final class ContributeController
{
    public function index(): void
    {
        if (AuthService::user() === null) {
            redirect('/login');
        }

        echo View::render('contribute/index', [
            'title' => __('nav.fab.aportar'),
        ], 'layouts/app');
    }
}
