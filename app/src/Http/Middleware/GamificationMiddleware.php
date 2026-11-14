<?php

declare(strict_types=1);

namespace Cauce\Http\Middleware;

use Cauce\Helpers\Logger;
use Cauce\Helpers\View;
use Cauce\Services\AuthService;
use Cauce\Services\GamificationService;
use Throwable;

/**
 * Reconciliación perezosa de racha, nivel, retos y celebraciones.
 *
 * El trabajo pesado corre como máximo cada 5 minutos; la celebración pendiente
 * se consulta en cada petición para mostrarla en cuanto se detecte.
 */
final class GamificationMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        $user = AuthService::user();

        if ($user === null) {
            return true;
        }

        $service = new GamificationService();
        $enabled = $service->isEnabled($user);
        $onboarded = (int) ($user['onboarding_completado'] ?? 0) === 1;
        View::share(['gamificationEnabled' => $enabled]);

        $last = isset($_SESSION['_gamification_check']) ? (int) $_SESSION['_gamification_check'] : 0;

        if (time() - $last >= 300) {
            $_SESSION['_gamification_check'] = time();

            try {
                if ($enabled && $onboarded) {
                    $service->reconcile($user);
                    $service->refreshLevelIfDue($user);
                    $service->ensureChallenge($user);
                }
            } catch (Throwable $exception) {
                Logger::warning('No se pudo procesar la gamificación', [
                    'user_id' => $user['id'] ?? null,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        try {
            View::share(['pendingCelebration' => $service->pendingCelebration($user)]);
        } catch (Throwable $exception) {
            Logger::warning('No se pudo consultar la celebración pendiente', [
                'user_id' => $user['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }

        return true;
    }
}
