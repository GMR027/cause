<?php

declare(strict_types=1);

namespace Cauce\Http\Middleware;

use Cauce\Helpers\Logger;
use Cauce\Helpers\View;
use Cauce\Repositories\NotificationRepository;
use Cauce\Services\AuthService;
use Cauce\Services\NotificationService;
use Throwable;

/**
 * Procesa notificaciones pendientes como máximo cada 5 minutos.
 */
final class NotificationMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        $user = AuthService::user();

        if ($user === null) {
            return true;
        }

        $last = isset($_SESSION['_notifications_check']) ? (int) $_SESSION['_notifications_check'] : 0;

        if (time() - $last < 300) {
            return true;
        }

        $_SESSION['_notifications_check'] = time();

        try {
            (new NotificationService())->process($user);
            View::share([
                'unreadNotifications' => (new NotificationRepository())->unreadCount((int) $user['id']),
            ]);
        } catch (Throwable $exception) {
            Logger::warning('No se pudieron procesar las notificaciones', [
                'user_id' => $user['id'],
                'error' => $exception->getMessage(),
            ]);
        }

        return true;
    }
}
