<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\View;
use Cauce\Services\AuthService;

/**
 * Información del producto, principios y estado del proyecto.
 */
final class AboutController
{
    public function index(): void
    {
        $user = AuthService::user();

        if ($user === null) {
            redirect('/login');
        }

        echo View::render('about/index', [
            'title' => __('about.title'),
            'version' => (string) config('app.version', '0.0.0'),
        ], 'layouts/app');
    }
}
