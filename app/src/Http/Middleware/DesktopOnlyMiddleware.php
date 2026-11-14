<?php

declare(strict_types=1);

namespace Cauce\Http\Middleware;

use Cauce\Helpers\Client;
use Cauce\Helpers\View;

/**
 * Doble candado de Estado de cuenta: el backend rechaza UA móviles/tablet.
 */
final class DesktopOnlyMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        if (Client::isDesktop()) {
            return true;
        }

        http_response_code(403);
        echo View::render('errors/403', [
            'title' => __('common.errors.403.title'),
            'message' => __('statements.errors.desktop_only'),
        ]);

        return false;
    }
}
