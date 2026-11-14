<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\Logger;
use Cauce\Helpers\View;
use Cauce\Services\AnalyticsService;
use Cauce\Services\AuthService;
use Cauce\Services\LoginThrottleService;
use Cauce\Services\StatementService;
use Throwable;

/**
 * Estado de cuenta: consentimiento, reautenticación, PDFs y visor.
 */
final class StatementController
{
    private StatementService $service;

    public function __construct()
    {
        $this->service = new StatementService();
    }

    public function index(): void
    {
        $user = $this->user();
        $consent = $this->service->hasConsent($user);
        $reauth = $this->service->reauthValid();
        $usage = $this->service->usage($user);

        (new AnalyticsService())->track('statement_view', [], $user);

        echo View::render('statements/index', [
            'title' => __('statements.title'),
            'consent' => $consent,
            'reauth' => $reauth,
            'documents' => $consent && $reauth ? $this->service->all($user) : [],
            'usage' => $usage,
            'maxBytes' => (int) config('security.statements.max_bytes', 10485760),
            'maxFiles' => (int) config('security.statements.max_files', 240),
            'consentVersion' => StatementService::consentVersion(),
            'errors' => [],
        ], 'layouts/app');
    }

    public function acceptConsent(): void
    {
        $user = $this->user();

        if (empty($_POST['acepto'])) {
            Flash::error('statements.errors.consent_required');
            redirect('/estado-cuenta');
        }

        $this->service->acceptConsent($user);
        (new AnalyticsService())->track('statement_consent', [
            'version' => StatementService::consentVersion(),
        ], $user);
        Flash::success('statements.flash.consent_saved');

        redirect('/estado-cuenta');
    }

    public function authenticate(): void
    {
        $user = $this->user();
        $password = (string) input('password', '');
        $throttle = new LoginThrottleService();

        if ($throttle->tooManyAttempts((string) $user['email'])) {
            Flash::error('auth.errors.too_many_attempts');
            redirect('/estado-cuenta');
        }

        if (!AuthService::verifyPassword($user, $password)) {
            $throttle->record((string) $user['email'], false);
            Flash::error('statements.errors.invalid_password');
            redirect('/estado-cuenta');
        }

        $throttle->record((string) $user['email'], true);
        $this->service->markReauthenticated();
        (new AnalyticsService())->track('statement_reauth', [], $user);

        redirect('/estado-cuenta');
    }

    public function upload(): void
    {
        $user = $this->user();

        if (!$this->service->hasConsent($user) || !$this->service->reauthValid()) {
            Flash::error('statements.errors.reauth_required');
            redirect('/estado-cuenta');
        }

        try {
            $this->service->store(
                $user,
                is_array($_FILES['archivo'] ?? null) ? $_FILES['archivo'] : [],
                (string) input('tipo', 'banco')
            );
            (new AnalyticsService())->track('statement_upload', [], $user);
            Flash::success('statements.flash.uploaded');
        } catch (Throwable $exception) {
            Logger::warning('No se pudo guardar el estado de cuenta', [
                'user_id' => $user['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
            Flash::error('statements.errors.upload_failed', [':message' => __($this->uploadErrorKey($exception))]);
        }

        redirect('/estado-cuenta');
    }

    public function view(): void
    {
        $user = $this->user();

        if (!$this->service->hasConsent($user) || !$this->service->reauthValid()) {
            http_response_code(403);
            echo View::render('errors/403', [
                'title' => __('common.errors.403.title'),
                'message' => __('statements.errors.reauth_required'),
            ]);

            return;
        }

        $id = (int) input('id', '0');
        $row = $this->service->find($user, $id);
        $path = $row !== null ? $this->service->path($row) : null;

        if ($row === null || $path === null) {
            http_response_code(404);
            echo View::render('errors/404', ['title' => __('common.errors.404.title')]);

            return;
        }

        $name = basename((string) $row['nombre_original']);
        $name = str_replace(["\r", "\n", '"'], '', $name);

        (new AnalyticsService())->track('statement_open', [], $user);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        readfile($path);
        exit;
    }

    public function delete(): void
    {
        $user = $this->user();
        $id = (int) input('id', '0');

        if (!$this->service->hasConsent($user) || !$this->service->reauthValid()) {
            Flash::error('statements.errors.reauth_required');
            redirect('/estado-cuenta');
        }

        if ($this->service->find($user, $id) === null) {
            Flash::error('statements.errors.not_found');
            redirect('/estado-cuenta');
        }

        $this->service->delete($user, $id);
        (new AnalyticsService())->track('statement_delete', [], $user);
        Flash::success('statements.flash.deleted');

        redirect('/estado-cuenta');
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

    private function uploadErrorKey(Throwable $exception): string
    {
        return match ($exception->getMessage()) {
            'No se recibió ningún archivo.' => 'statements.upload_errors.no_file',
            'No se pudo subir el archivo.' => 'statements.upload_errors.upload',
            'Alcanzaste el límite de 240 estados de cuenta.' => 'statements.upload_errors.limit',
            'El archivo debe tener extensión .pdf.' => 'statements.upload_errors.extension',
            'El archivo no es válido.' => 'statements.upload_errors.invalid',
            'El archivo está vacío.' => 'statements.upload_errors.empty',
            'El archivo supera el tamaño máximo de 10 MB.' => 'statements.upload_errors.size',
            'El archivo no es un PDF válido.' => 'statements.upload_errors.not_pdf',
            default => 'statements.upload_errors.generic',
        };
    }
}
