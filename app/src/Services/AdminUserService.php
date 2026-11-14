<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Helpers\PasswordHasher;
use Cauce\Repositories\SessionRepository;
use Cauce\Repositories\UserRepository;
use PDO;
use RuntimeException;

/**
 * Acciones administrativas sobre usuarios (solo metadatos).
 */
final class AdminUserService
{
    private UserRepository $users;

    private PDO $db;

    public function __construct(?UserRepository $users = null, ?PDO $db = null)
    {
        $this->users = $users ?? new UserRepository();
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return array{users: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function search(string $query, int $page = 1, int $perPage = 50): array
    {
        $perPage = max(10, min(100, $perPage));
        $total = $this->users->countSearch($query);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($pages, $page));

        return [
            'users' => $this->users->search($query, $perPage, ($page - 1) * $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function detail(array $user): array
    {
        $userId = (int) $user['id'];
        $onboarding = (new OnboardingService())->state($user);
        $safeOnboarding = [
            'paso' => (string) ($onboarding['paso'] ?? ''),
            'metodo' => (string) ($onboarding['metodo'] ?? ''),
            'moneda' => (string) ($onboarding['moneda'] ?? ''),
            'tipo_ingreso' => (string) ($onboarding['tipo_ingreso'] ?? ''),
            'configuracion' => (string) ($onboarding['configuracion'] ?? ''),
            'recurrencia' => (string) ($onboarding['recurrencia'] ?? ''),
            'tutorial_visto' => !empty($onboarding['tutorial_visto']),
            'completed_at' => $onboarding['completed_at'] ?? null,
        ];

        $sessions = $this->db->prepare('SELECT COUNT(*) FROM sesiones WHERE user_id = ?');
        $sessions->execute([$userId]);

        $hogar = null;

        if (!empty($user['hogar_id'])) {
            $statement = $this->db->prepare(
                'SELECT h.nombre, hm.rol FROM hogares h
                 LEFT JOIN hogar_miembros hm ON hm.hogar_id = h.id AND hm.user_id = ?
                 WHERE h.id = ? LIMIT 1'
            );
            $statement->execute([$userId, (int) $user['hogar_id']]);
            $row = $statement->fetch();
            $hogar = is_array($row) ? $row : null;
        }

        return [
            'user' => $user,
            'session_count' => (int) $sessions->fetchColumn(),
            'statements' => (new StatementService())->usage($user),
            'onboarding' => $safeOnboarding,
            'hogar' => $hogar,
        ];
    }

    /**
     * @param array<string, mixed> $admin
     * @param array<string, mixed> $target
     */
    public function suspend(array $admin, array $target, string $reason): void
    {
        $this->assertCanManage($admin, $target);

        if (($target['rol'] ?? 'user') === 'admin' && $this->users->activeAdminCount() <= 1) {
            throw new RuntimeException('No se puede suspender al último administrador activo.');
        }

        $this->users->setEstado((int) $target['id'], 'suspendido', mb_substr(trim($reason), 0, 255));
        (new SessionRepository())->deleteAllForUser((int) $target['id']);
        AdminAuditService::log('admin.users.suspend', 'users', (int) $target['id'], [
            'alias' => (string) $target['alias'],
            'reason' => mb_substr(trim($reason), 0, 255),
        ]);
    }

    /**
     * @param array<string, mixed> $admin
     * @param array<string, mixed> $target
     */
    public function reactivate(array $admin, array $target): void
    {
        $this->assertNotSelf($admin, $target);
        $this->users->setEstado((int) $target['id'], 'activo');
        AdminAuditService::log('admin.users.reactivate', 'users', (int) $target['id'], [
            'alias' => (string) $target['alias'],
        ]);
    }

    /**
     * @param array<string, mixed> $admin
     * @param array<string, mixed> $target
     */
    public function forceLogout(array $admin, array $target): void
    {
        (new SessionRepository())->deleteAllForUser((int) $target['id']);
        AdminAuditService::log('admin.users.force_logout', 'users', (int) $target['id'], [
            'alias' => (string) $target['alias'],
        ]);
    }

    /**
     * @param array<string, mixed> $admin
     * @param array<string, mixed> $target
     */
    public function delete(array $admin, array $target): void
    {
        $this->assertCanManage($admin, $target);
        (new AccountDeletionService())->delete($target, (int) $admin['id']);
    }

    /**
     * @param array<string, mixed> $admin
     * @param array<string, mixed> $target
     */
    public function resetPassword(array $admin, array $target): string
    {
        $this->assertNotSelf($admin, $target);

        $temporary = 'Cauce-' . bin2hex(random_bytes(4)) . '7!';
        $this->users->updatePassword((int) $target['id'], PasswordHasher::hash($temporary), true);
        (new SessionRepository())->deleteAllForUser((int) $target['id']);
        AdminAuditService::log('admin.users.reset_password', 'users', (int) $target['id'], [
            'alias' => (string) $target['alias'],
            'temporary' => true,
        ]);

        return $temporary;
    }

    /**
     * @param array<string, mixed> $admin
     * @param array<string, mixed> $target
     */
    public function changeEmail(array $admin, array $target, string $email): void
    {
        $email = mb_strtolower(trim($email));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 190) {
            throw new RuntimeException('El correo no es válido.');
        }

        $existing = $this->users->findByEmail($email);

        if ($existing !== null && (int) $existing['id'] !== (int) $target['id']) {
            throw new RuntimeException('Ese correo ya está en uso.');
        }

        $previous = (string) $target['email'];
        $this->users->updateEmail((int) $target['id'], $email);
        AdminAuditService::log('admin.users.change_email', 'users', (int) $target['id'], [
            'alias' => (string) $target['alias'],
            'previous' => $previous,
            'new' => $email,
        ]);
    }

    /**
     * @param array<string, mixed> $admin
     * @param array<string, mixed> $target
     */
    public function createExportLink(array $admin, array $target): string
    {
        $token = (new ExportTokenService())->create((int) $target['id'], (int) $admin['id']);
        AdminAuditService::log('admin.users.export_link', 'users', (int) $target['id'], [
            'alias' => (string) $target['alias'],
        ]);

        return '/exportar/descargar?token=' . rawurlencode($token);
    }

    /**
     * @param array<string, mixed> $admin
     * @param array<string, mixed> $target
     */
    private function assertCanManage(array $admin, array $target): void
    {
        $this->assertNotSelf($admin, $target);
    }

    /**
     * @param array<string, mixed> $admin
     * @param array<string, mixed> $target
     */
    private function assertNotSelf(array $admin, array $target): void
    {
        if ((int) $admin['id'] === (int) $target['id']) {
            throw new RuntimeException('No puedes aplicar esta acción sobre tu propia cuenta.');
        }
    }
}
