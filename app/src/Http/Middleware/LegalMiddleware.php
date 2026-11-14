<?php

declare(strict_types=1);

namespace Cauce\Http\Middleware;

use Cauce\Services\AuthService;
use Cauce\Services\LegalService;

/**
 * Exige aceptar la versión vigente de privacidad y términos.
 */
final class LegalMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        $user = AuthService::user();

        if ($user === null) {
            return true;
        }

        // Durante el onboarding todavía no exigimos la aceptación: el usuario
        // primero guarda su código de recuperación y completa su método.
        if ((int) ($user['onboarding_completado'] ?? 0) !== 1) {
            return true;
        }

        $path = current_path();
        $allowed = ['/legal/aceptar', '/logout', '/desbloquear', '/ajustes/seguridad/cambiar-password'];

        if (in_array($path, $allowed, true)) {
            return true;
        }

        try {
            $pending = (new LegalService())->pending($user);
        } catch (\Throwable) {
            return true;
        }

        if ($pending === []) {
            return true;
        }

        $_SESSION['_legal_redirect'] = (string) ($_SERVER['REQUEST_URI'] ?? '/inicio');
        redirect('/legal/aceptar');
    }
}
