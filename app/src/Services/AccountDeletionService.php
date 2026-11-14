<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Helpers\Cache;
use Cauce\Helpers\Logger;
use Cauce\Helpers\Storage;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Borrado definitivo de una cuenta y sus archivos.
 */
final class AccountDeletionService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @param array<string, mixed> $user
     */
    public function delete(array $user, ?int $actorId = null): void
    {
        $userId = (int) $user['id'];

        if (($user['rol'] ?? 'user') === 'admin' && (new \Cauce\Repositories\UserRepository())->activeAdminCount() <= 1) {
            throw new RuntimeException('No se puede eliminar al último administrador activo.');
        }

        $hogarId = isset($user['hogar_id']) ? (int) $user['hogar_id'] : 0;

        $this->db->beginTransaction();

        try {
            $this->db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }

        // Los archivos se eliminan después del commit; si algo falla, solo
        // quedan huérfanos y se registra para limpieza manual.
        try {
            Storage::deleteDirectory(base_path('storage/uploads/gastos/' . $userId));
            Storage::deleteDirectory(CoverService::userDirectory($userId));
            (new StatementService())->purge($user);
        } catch (Throwable $exception) {
            Logger::warning('No se pudieron borrar todos los archivos de la cuenta', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
        }

        if ($hogarId > 0) {
            $members = $this->db->prepare('SELECT COUNT(*) FROM hogar_miembros WHERE hogar_id = ?');
            $members->execute([$hogarId]);

            if ((int) $members->fetchColumn() === 0) {
                $this->db->prepare('DELETE FROM hogares WHERE id = ?')->execute([$hogarId]);
            }
        }

        if ($actorId !== null && $actorId !== $userId) {
            AdminAuditService::logFor($actorId, 'admin.users.delete', 'users', $userId, [
                'alias' => (string) $user['alias'],
                'email' => (string) $user['email'],
            ]);
        }

        Cache::forget('admin_stats_v2');

        Logger::info('Cuenta eliminada', [
            'user_id' => $userId,
            'actor_id' => $actorId,
        ]);
    }
}
