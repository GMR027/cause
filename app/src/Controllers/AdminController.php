<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Config\Database;
use Cauce\Helpers\Flash;
use Cauce\Helpers\I18n;
use Cauce\Helpers\Logger;
use Cauce\Helpers\PasswordHasher;
use Cauce\Helpers\Session;
use Cauce\Helpers\Validator;
use Cauce\Helpers\View;
use Cauce\Repositories\AdminAuditRepository;
use Cauce\Repositories\SubcategoriaRepository;
use Cauce\Repositories\UserRepository;
use Cauce\Services\AdminAuditService;
use Cauce\Services\RecoveryService;
use PDOException;
use Throwable;

final class AdminController
{
    private UserRepository $users;

    private AdminAuditRepository $audits;

    private RecoveryService $recovery;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->audits = new AdminAuditRepository();
        $this->recovery = new RecoveryService();
    }

    public function index(): void
    {
        echo View::render('admin/panel', [
            'title' => __('common.admin.panel'),
            'userCount' => $this->users->count(),
            'auditCount' => $this->audits->count(),
            'recentAudit' => $this->audits->recent(10),
        ], 'layouts/app');
    }

    public function users(): void
    {
        $code = Session::pull('_admin_recovery_code');

        echo View::render('admin/users', [
            'title' => __('common.admin.users'),
            'users' => $this->users->all(),
            'recoveryCode' => is_array($code) ? $code : null,
        ], 'layouts/app');
    }

    public function createUserForm(): void
    {
        echo View::render('admin/create-user', [
            'title' => __('common.admin.new_user'),
            'old' => [
                'alias' => '',
                'email' => '',
                'idioma' => I18n::current(),
                'rol' => 'user',
            ],
            'errors' => [],
        ], 'layouts/app');
    }

    public function storeUser(): void
    {
        $old = [
            'alias' => (string) input('alias', ''),
            'email' => strtolower((string) input('email', '')),
            'idioma' => (string) input('idioma', I18n::current()),
            'rol' => (string) input('rol', 'user'),
        ];

        $validator = new Validator([
            'alias' => $old['alias'],
            'email' => $old['email'],
            'password' => input('password', '') ?? '',
            'idioma' => $old['idioma'],
            'rol' => $old['rol'],
        ]);

        $validator
            ->required('alias')
            ->minLength('alias', 3)
            ->maxLength('alias', 30)
            ->regex('alias', '/^[A-Za-z0-9_-]+$/', null, 'auth.errors.alias_format')
            ->required('email')
            ->email('email')
            ->maxLength('email', 190)
            ->password('password')
            ->in('idioma', I18n::SUPPORTED)
            ->in('rol', ['user', 'admin']);

        $errors = $validator->errors();

        if ($errors === [] && $this->users->aliasExists($old['alias'])) {
            $errors[] = __('auth.errors.alias_taken');
        }

        if ($errors === [] && $this->users->emailExists($old['email'])) {
            $errors[] = __('auth.errors.email_taken');
        }

        if ($errors !== []) {
            AdminAuditService::log('admin.users.create_failed', 'users', null, [
                'alias' => $old['alias'],
                'email' => $old['email'],
                'errors' => $errors,
            ]);

            echo View::render('admin/create-user', [
                'title' => __('common.admin.new_user'),
                'old' => $old,
                'errors' => $errors,
            ], 'layouts/app');

            return;
        }

        $db = Database::connection();

        try {
            $db->beginTransaction();

            $userId = $this->users->create([
                'alias' => $old['alias'],
                'email' => $old['email'],
                'password_hash' => PasswordHasher::hash((string) input('password', '')),
                'idioma' => $old['idioma'],
                'rol' => $old['rol'],
                'onboarding_completado' => 0,
            ]);

            (new SubcategoriaRepository($db))->seedForUser($userId);
            $code = $this->recovery->createCodeForUser($userId);

            $db->commit();
        } catch (PDOException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            Logger::warning('No se pudo crear el usuario desde admin', ['error' => $exception->getMessage()]);

            $message = str_contains($exception->getMessage(), 'uq_users_alias')
                ? __('auth.errors.alias_taken')
                : (str_contains($exception->getMessage(), 'uq_users_email')
                    ? __('auth.errors.email_taken')
                    : __('auth.errors.generic'));

            echo View::render('admin/create-user', [
                'title' => __('common.admin.new_user'),
                'old' => $old,
                'errors' => [$message],
            ], 'layouts/app');

            return;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            Logger::error('Error al crear usuario desde admin', ['error' => $exception->getMessage()]);

            echo View::render('admin/create-user', [
                'title' => __('common.admin.new_user'),
                'old' => $old,
                'errors' => [__('auth.errors.generic')],
            ], 'layouts/app');

            return;
        }

        AdminAuditService::log('admin.users.create', 'users', $userId, [
            'alias' => $old['alias'],
            'email' => $old['email'],
            'rol' => $old['rol'],
        ]);

        Session::set('_admin_recovery_code', [
            'code' => $this->recovery->formatCode($code),
            'alias' => $old['alias'],
        ]);

        Flash::success('common.admin.user_created');
        redirect('/admin/usuarios');
    }

    public function resetUser(): void
    {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $user = $this->users->findById($userId);

        if ($user === null) {
            Flash::error('common.admin.user_not_found');
            redirect('/admin/usuarios');
        }

        $db = Database::connection();

        try {
            $db->beginTransaction();

            (new \Cauce\Repositories\RecoveryRepository($db))->invalidateCodes($userId);
            $code = $this->recovery->createCodeForUser($userId);

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            Logger::error('No se pudo forzar el reseteo', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            Flash::error('auth.errors.generic');
            redirect('/admin/usuarios');
        }

        AdminAuditService::log('admin.users.reset', 'users', $userId, [
            'alias' => $user['alias'],
            'email' => $user['email'],
        ]);

        Session::set('_admin_recovery_code', [
            'code' => $this->recovery->formatCode($code),
            'alias' => (string) $user['alias'],
        ]);

        Flash::success('common.admin.user_reset');
        redirect('/admin/usuarios');
    }

    public function audit(): void
    {
        echo View::render('admin/audit', [
            'title' => __('common.admin.audit'),
            'entries' => $this->audits->recent(200),
        ], 'layouts/app');
    }
}
