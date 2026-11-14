<?php

declare(strict_types=1);

namespace Cauce\Http\Middleware;

use Cauce\Helpers\Logger;
use Cauce\Services\AuthService;
use Cauce\Services\RecurringExpenseService;
use Throwable;

/**
 * Ejecuta los gastos recurrentes vencidos, como máximo una vez por minuto.
 */
final class RecurringExpenseMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        $user = AuthService::user();

        if ($user === null) {
            return true;
        }

        $last = isset($_SESSION['_recurring_check']) ? (int) $_SESSION['_recurring_check'] : 0;

        if (time() - $last < 60) {
            return true;
        }

        $_SESSION['_recurring_check'] = time();

        try {
            (new RecurringExpenseService())->runDueForUser($user);
        } catch (Throwable $exception) {
            Logger::warning('No se pudieron procesar los recurrentes', [
                'user_id' => $user['id'],
                'error' => $exception->getMessage(),
            ]);
        }

        return true;
    }
}
