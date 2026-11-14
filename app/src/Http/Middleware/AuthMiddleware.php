<?php

declare(strict_types=1);

namespace Cauce\Http\Middleware;

use Cauce\Services\AuthService;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        if (!AuthService::check()) {
            redirect('/login');
        }

        return true;
    }
}
