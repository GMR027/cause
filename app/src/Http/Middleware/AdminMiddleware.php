<?php

declare(strict_types=1);

namespace Cauce\Http\Middleware;

use Cauce\Helpers\View;
use Cauce\Services\AdminAuditService;
use Cauce\Services\AuthService;

final class AdminMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        $user = AuthService::user();

        if ($user === null) {
            redirect('/login');
        }

        if (($user['rol'] ?? 'user') !== 'admin') {
            AdminAuditService::logFor((int) $user['id'], 'admin.access.denied', 'ruta', null, [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                'path' => parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
            ]);

            http_response_code(403);
            echo View::render('errors/403', ['title' => __('common.errors.403.title')]);

            return false;
        }

        AdminAuditService::log('admin.access', 'ruta', null, [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'path' => parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
        ]);

        return true;
    }
}
