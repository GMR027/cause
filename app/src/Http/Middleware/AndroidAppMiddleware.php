<?php

declare(strict_types=1);

namespace Cauce\Http\Middleware;

use Cauce\Helpers\Logger;
use Cauce\Helpers\View;
use Cauce\Services\AndroidAppService;
use Cauce\Services\AuthService;
use Throwable;

/**
 * Si la petición viene de la app Android (cookie cauce_android), anota el
 * teléfono y comparte con el layout la versión nueva cuando la instalada es vieja.
 */
final class AndroidAppMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        $app = AndroidAppService::fromApp();

        if ($app === null) {
            return true;
        }

        try {
            $service = new AndroidAppService();
            $user = AuthService::user();
            $service->recordVisit($user !== null ? (int) $user['id'] : null);
            $latest = $service->latest();

            if (AndroidAppService::isOutdated($app['version'], $latest)) {
                View::share(['androidUpdate' => ['instalada' => $app['version'], 'nueva' => (string) $latest['version']]]);
            }
        } catch (Throwable $exception) {
            Logger::warning('No se revisó la versión de la app Android', ['error' => $exception->getMessage()]);
        }

        return true;
    }
}
