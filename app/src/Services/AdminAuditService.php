<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Helpers\Logger;
use Cauce\Repositories\AdminAuditRepository;
use Throwable;

/**
 * Registro de acciones administrativas.
 */
final class AdminAuditService
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function log(
        string $action,
        ?string $entity = null,
        ?int $entityId = null,
        array $details = []
    ): void {
        $user = AuthService::user();

        if ($user === null) {
            return;
        }

        self::logFor((int) $user['id'], $action, $entity, $entityId, $details);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function logFor(
        int $adminId,
        string $action,
        ?string $entity = null,
        ?int $entityId = null,
        array $details = []
    ): void {
        try {
            (new AdminAuditRepository())->log($adminId, $action, $entity, $entityId, $details);
        } catch (Throwable $exception) {
            Logger::warning('No se pudo escribir en admin_audit_log', [
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
