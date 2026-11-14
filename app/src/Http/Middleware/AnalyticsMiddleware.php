<?php

declare(strict_types=1);

namespace Cauce\Http\Middleware;

use Cauce\Services\AnalyticsService;
use Cauce\Services\AuthService;

/**
 * Registra vistas de página anónimas para las estadísticas del admin.
 */
final class AnalyticsMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
            return true;
        }

        $user = AuthService::user();

        if ($user === null || (int) ($user['analytics_opt_out'] ?? 0) === 1) {
            return true;
        }

        $service = new AnalyticsService();
        $service->trackPath($user, current_path());

        if (random_int(1, 200) === 1) {
            $service->prune();
        }

        return true;
    }
}
