<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\View;
use Cauce\Services\AuthService;

/**
 * Landing pública de Cauce.
 */
final class HomeController
{
    public function index(): void
    {
        if (AuthService::check()) {
            redirect('/inicio');
        }

        echo View::render('home', [
            'title' => __('landing.meta_title'),
        ], 'layouts/landing');
    }
}
