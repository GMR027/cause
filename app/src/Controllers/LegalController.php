<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\Session;
use Cauce\Helpers\View;
use Cauce\Services\AnalyticsService;
use Cauce\Services\AuthService;
use Cauce\Services\LegalService;
use Throwable;

/**
 * Documentos legales públicos y aceptación autenticada.
 */
final class LegalController
{
    public function privacy(): void
    {
        $this->show('privacidad');
    }

    public function terms(): void
    {
        $this->show('terminos');
    }

    public function notice(): void
    {
        $this->show('aviso');
    }

    public function acceptForm(): void
    {
        $user = $this->user();
        $service = new LegalService();
        $pending = $service->pending($user);

        if ($pending === []) {
            redirect('/inicio');
        }

        echo View::render('legal/accept', [
            'title' => __('legal.accept.title'),
            'documents' => $pending,
            'errors' => [],
        ], 'layouts/app');
    }

    public function accept(): void
    {
        $user = $this->user();
        $service = new LegalService();
        $pending = $service->pending($user);

        if ($pending === []) {
            redirect('/inicio');
        }

        if (empty($_POST['acepto'])) {
            echo View::render('legal/accept', [
                'title' => __('legal.accept.title'),
                'documents' => $pending,
                'errors' => [__('legal.accept.required')],
            ], 'layouts/app');

            return;
        }

        try {
            $service->accept($user, $pending);
            (new AnalyticsService())->track('legal_accepted', [
                'version' => implode(',', array_column($pending, 'version')),
            ], $user);
            Flash::success('legal.accept.accepted');
        } catch (Throwable) {
            Flash::error('legal.accept.error');
            redirect('/legal/aceptar');
        }

        $next = Session::pull('_legal_redirect', '/inicio');

        if (!is_string($next) || !str_starts_with($next, '/') || str_starts_with($next, '//')
            || str_contains($next, '\\')
        ) {
            $next = '/inicio';
        }

        redirect($next);
    }

    private function show(string $type): void
    {
        $document = (new LegalService())->currentFor($type);

        if ($document === null) {
            http_response_code(404);
            echo View::render('errors/404', ['title' => __('common.errors.404.title')], 'layouts/base');

            return;
        }

        $lang = current_lang();

        echo View::render('legal/show', [
            'title' => LegalService::title($document, $lang),
            'document' => $document,
            'content' => LegalService::content($document, $lang),
        ], 'layouts/base');
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
