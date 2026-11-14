<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\Session;
use Cauce\Helpers\View;
use Cauce\Repositories\UserRepository;
use Cauce\Services\AdminUserService;
use Cauce\Services\AuthService;
use Throwable;

/**
 * Gestión administrativa de usuarios: metadatos, acciones y ARCO.
 */
final class AdminUserController
{
    private AdminUserService $service;

    private UserRepository $users;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->service = new AdminUserService($this->users);
    }

    public function index(): void
    {
        $query = (string) input('q', '');
        $page = max(1, (int) input('page', '1'));
        $result = $this->service->search($query, $page);

        echo View::render('admin/users', [
            'title' => __('common.admin.users'),
            'query' => $query,
            'users' => $result['users'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'recoveryCode' => null,
        ], 'layouts/app');
    }

    public function show(): void
    {
        $target = $this->target();
        $detail = $this->service->detail($target);

        echo View::render('admin/user-detail', array_merge($detail, [
            'title' => __('admin.users.detail_title'),
            'tempPassword' => Session::pull('_admin_temp_password'),
            'exportLink' => Session::pull('_admin_export_link'),
            'errors' => [],
        ]), 'layouts/app');
    }

    public function suspend(): void
    {
        $admin = $this->admin();
        $target = $this->target();
        $reason = (string) input('motivo', '');

        if (trim($reason) === '') {
            Flash::error('admin.users.errors.reason_required');
            redirect('/admin/usuarios/ver?id=' . (int) $target['id']);
        }

        try {
            $this->service->suspend($admin, $target, $reason);
            Flash::success('admin.users.flash.suspended');
        } catch (Throwable $exception) {
            Flash::error('admin.users.errors.generic', [':message' => $exception->getMessage()]);
        }

        redirect('/admin/usuarios/ver?id=' . (int) $target['id']);
    }

    public function reactivate(): void
    {
        $admin = $this->admin();
        $target = $this->target();

        try {
            $this->service->reactivate($admin, $target);
            Flash::success('admin.users.flash.reactivated');
        } catch (Throwable $exception) {
            Flash::error('admin.users.errors.generic', [':message' => $exception->getMessage()]);
        }

        redirect('/admin/usuarios/ver?id=' . (int) $target['id']);
    }

    public function forceLogout(): void
    {
        $admin = $this->admin();
        $target = $this->target();
        $this->service->forceLogout($admin, $target);
        Flash::success('admin.users.flash.sessions_closed');

        redirect('/admin/usuarios/ver?id=' . (int) $target['id']);
    }

    public function resetPassword(): void
    {
        $admin = $this->admin();
        $target = $this->target();

        try {
            $temporary = $this->service->resetPassword($admin, $target);
            Session::set('_admin_temp_password', [
                'alias' => (string) $target['alias'],
                'password' => $temporary,
            ]);
            Flash::success('admin.users.flash.password_reset');
        } catch (Throwable $exception) {
            Flash::error('admin.users.errors.generic', [':message' => $exception->getMessage()]);
        }

        redirect('/admin/usuarios/ver?id=' . (int) $target['id']);
    }

    public function changeEmail(): void
    {
        $admin = $this->admin();
        $target = $this->target();

        try {
            $this->service->changeEmail($admin, $target, (string) input('email', ''));
            Flash::success('admin.users.flash.email_changed');
        } catch (Throwable $exception) {
            Flash::error('admin.users.errors.generic', [':message' => $exception->getMessage()]);
        }

        redirect('/admin/usuarios/ver?id=' . (int) $target['id']);
    }

    public function exportLink(): void
    {
        $admin = $this->admin();
        $target = $this->target();

        try {
            $link = $this->service->createExportLink($admin, $target);
            Session::set('_admin_export_link', [
                'alias' => (string) $target['alias'],
                'url' => $link,
            ]);
            Flash::success('admin.users.flash.export_link');
        } catch (Throwable $exception) {
            Flash::error('admin.users.errors.generic', [':message' => $exception->getMessage()]);
        }

        redirect('/admin/usuarios/ver?id=' . (int) $target['id']);
    }

    public function delete(): void
    {
        $admin = $this->admin();
        $target = $this->target();

        if (trim((string) input('confirmacion', '')) !== 'ELIMINAR' || empty($_POST['confirmo'])) {
            Flash::error('admin.users.errors.delete_confirmation');
            redirect('/admin/usuarios/ver?id=' . (int) $target['id']);
        }

        try {
            $this->service->delete($admin, $target);
            Flash::success('admin.users.flash.deleted');
            redirect('/admin/usuarios');
        } catch (Throwable $exception) {
            Flash::error('admin.users.errors.generic', [':message' => $exception->getMessage()]);
            redirect('/admin/usuarios/ver?id=' . (int) $target['id']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function target(): array
    {
        $user = $this->users->findById((int) input('id', '0'));

        if ($user === null) {
            Flash::error('common.admin.user_not_found');
            redirect('/admin/usuarios');
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function admin(): array
    {
        $user = AuthService::user();

        if ($user === null) {
            redirect('/login');
        }

        return $user;
    }
}
