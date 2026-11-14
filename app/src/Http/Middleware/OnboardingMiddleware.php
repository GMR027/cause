<?php

declare(strict_types=1);

namespace Cauce\Http\Middleware;

use Cauce\Services\AuthService;

/**
 * Obliga a completar el onboarding antes de usar la app.
 */
final class OnboardingMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        $user = AuthService::user();

        if ($user === null) {
            redirect('/login');
        }

        if ((int) ($user['onboarding_completado'] ?? 0) !== 1
            && !str_starts_with(current_path(), '/onboarding')
        ) {
            redirect('/onboarding');
        }

        return true;
    }
}
