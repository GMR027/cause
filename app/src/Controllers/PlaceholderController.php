<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\View;
use Cauce\Services\AuthService;

/**
 * Pantallas aún no construidas. Se resuelven desde app/config/navigation.php
 * para no duplicar decenas de vistas casi idénticas.
 */
final class PlaceholderController
{
    public function show(): void
    {
        if (AuthService::user() === null) {
            redirect('/login');
        }

        $path = current_path();
        $placeholders = config('navigation.placeholders', []);
        $meta = is_array($placeholders) ? ($placeholders[$path] ?? null) : null;

        if (!is_array($meta)) {
            http_response_code(404);
            echo View::render('errors/404', [
                'title' => __('common.errors.404.title'),
                'path' => $path,
            ]);

            return;
        }

        $isAdmin = str_starts_with($path, '/admin');

        echo View::render('app/placeholder', [
            'title' => __((string) $meta['title']),
            'placeholderTitle' => __((string) $meta['title']),
            'phase' => (int) ($meta['phase'] ?? 4),
            'backRoute' => $isAdmin ? '/admin' : '/inicio',
        ], 'layouts/app');
    }
}
