<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\View;
use Cauce\Services\AdminAuditService;
use Cauce\Services\AuthService;
use Cauce\Services\SupportService;
use Throwable;

/**
 * Bandeja de soporte del administrador.
 */
final class AdminSupportController
{
    private SupportService $service;

    public function __construct()
    {
        $this->service = new SupportService();
    }

    public function index(): void
    {
        $filter = (string) input('filtro', 'todas');
        $filter = in_array($filter, ['todas', 'abiertas', 'cerradas'], true) ? $filter : 'todas';

        echo View::render('admin/support/index', [
            'title' => __('support.admin.title'),
            'conversations' => $this->service->adminList($filter),
            'filter' => $filter,
            'unread' => $this->service->unreadForAdmin(),
        ], 'layouts/app');
    }

    public function show(): void
    {
        $id = (int) input('id', '0');
        $conversation = $this->service->find($id);

        if ($conversation === null) {
            Flash::error('support.errors.not_found');
            redirect('/admin/soporte');
        }

        $this->service->markAdminRead($id);

        echo View::render('admin/support/show', [
            'title' => __('support.admin.thread_title'),
            'conversation' => $conversation,
            'messages' => $this->service->messages($id),
        ], 'layouts/app');
    }

    public function reply(): void
    {
        $admin = $this->user();
        $id = (int) input('id', '0');

        try {
            $this->service->adminReply($admin, $id, (string) input('mensaje', ''));
            AdminAuditService::log('admin.support.reply', 'soporte_conversaciones', $id);
            Flash::success('support.admin.flash.replied');
        } catch (Throwable $exception) {
            $key = $this->errorKey($exception);
            Flash::error($key);

            if ($key === 'support.errors.not_found') {
                redirect('/admin/soporte');
            }
        }

        redirect('/admin/soporte/ver?id=' . $id);
    }

    public function status(): void
    {
        $id = (int) input('id', '0');
        $status = (string) input('estado', '');

        if (!in_array($status, ['abierta', 'cerrada'], true)) {
            Flash::error('support.errors.invalid_status');
            redirect('/admin/soporte/ver?id=' . $id);
        }

        try {
            if ($status === 'cerrada') {
                $this->service->close($id);
                Flash::success('support.admin.flash.closed');
            } else {
                $this->service->reopen($id);
                Flash::success('support.admin.flash.reopened');
            }

            AdminAuditService::log('admin.support.status', 'soporte_conversaciones', $id, [
                'estado' => $status === 'cerrada' ? 'cerrada' : 'abierta',
            ]);
        } catch (Throwable $exception) {
            $key = $this->errorKey($exception);
            Flash::error($key);

            if ($key === 'support.errors.not_found') {
                redirect('/admin/soporte');
            }
        }

        redirect('/admin/soporte/ver?id=' . $id);
    }

    private function errorKey(Throwable $exception): string
    {
        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, 'no existe') => 'support.errors.not_found',
            str_contains($message, 'asunto') => 'support.errors.subject',
            str_contains($message, 'mensaje') => 'support.errors.message',
            default => 'support.errors.generic',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function user(): array
    {
        $user = AuthService::user();

        if ($user === null) {
            redirect('/login');
        }

        return $user;
    }
}
