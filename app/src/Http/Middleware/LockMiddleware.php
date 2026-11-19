<?php

declare(strict_types=1);

namespace Cauce\Http\Middleware;

use Cauce\Helpers\Session;
use Cauce\Services\AuthService;

/**
 * Bloquea la sesión tras un periodo de inactividad.
 *
 * Si el usuario configuró un PIN, puede desbloquear con él; en cualquier caso
 * la contraseña sigue siendo una alternativa válida.
 */
final class LockMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        $user = AuthService::user();

        if ($user === null) {
            return true;
        }

        $path = current_path();
        $allowed = ['/desbloquear', '/desbloquear/huella', '/desbloquear/huella/opciones', '/logout', '/ajustes/seguridad/cambiar-password'];

        if (in_array($path, $allowed, true)) {
            return true;
        }

        if ((int) ($user['must_change_password'] ?? 0) === 1) {
            Session::set('_lock_redirect', (string) ($_SERVER['REQUEST_URI'] ?? '/inicio'));
            redirect('/ajustes/seguridad/cambiar-password');
        }

        $minutes = max(1, (int) config('security.pin.unlock_minutes', 15));
        $lastSeen = isset($_SESSION['_last_seen_at']) ? (int) $_SESSION['_last_seen_at'] : 0;

        // Bloqueada (por tiempo o porque la pantalla abierta se bloqueó sola):
        // nada más se abre hasta desbloquear.
        if (!empty($_SESSION['_locked']) || ($lastSeen > 0 && time() - $lastSeen > $minutes * 60)) {
            $_SESSION['_locked'] = 1;
            if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
                Session::set('_lock_redirect', (string) ($_SERVER['REQUEST_URI'] ?? '/inicio'));
            }

            redirect('/desbloquear');
        }

        $_SESSION['_last_seen_at'] = time();

        return true;
    }
}
