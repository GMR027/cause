<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\View;
use Cauce\Services\AuthService;
use Cauce\Services\SupportService;
use Throwable;

/**
 * Bandeja de soporte del usuario.
 */
final class SupportController
{
    private SupportService $service;

    public function __construct()
    {
        $this->service = new SupportService();
    }

    public function index(): void
    {
        $user = $this->user();

        echo View::render('support/index', [
            'title' => __('support.title'),
            'conversations' => $this->service->userList($user),
            'unread' => $this->service->unreadForUser((int) $user['id']),
        ], 'layouts/app');
    }

    public function show(): void
    {
        $user = $this->user();
        $id = (int) input('id', '0');
        $conversation = $this->service->findForUser($id, (int) $user['id']);

        if ($conversation === null) {
            Flash::error('support.errors.not_found');
            redirect('/soporte');
        }

        $this->service->markUserRead($id, (int) $user['id']);

        echo View::render('support/show', [
            'title' => (string) $conversation['asunto'],
            'conversation' => $conversation,
            'messages' => $this->service->messages($id),
        ], 'layouts/app');
    }

    public function store(): void
    {
        $user = $this->user();

        try {
            $id = $this->service->create(
                $user,
                (string) input('asunto', ''),
                (string) input('mensaje', '')
            );
            Flash::success('support.flash.created');
            redirect('/soporte/ver?id=' . $id);
        } catch (Throwable $exception) {
            Flash::error($this->errorKey($exception));
        }

        redirect('/soporte');
    }

    public function reply(): void
    {
        $user = $this->user();
        $id = (int) input('id', '0');

        try {
            $this->service->reply($user, $id, (string) input('mensaje', ''));
            Flash::success('support.flash.replied');
        } catch (Throwable $exception) {
            $key = $this->errorKey($exception);
            Flash::error($key);

            if ($key === 'support.errors.not_found') {
                redirect('/soporte');
            }
        }

        redirect('/soporte/ver?id=' . $id);
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
