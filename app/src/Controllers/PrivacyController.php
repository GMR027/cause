<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\Logger;
use Cauce\Helpers\Session;
use Cauce\Helpers\View;
use Cauce\Services\AccountDeletionService;
use Cauce\Services\AnalyticsService;
use Cauce\Services\AuthService;
use Cauce\Services\ExportService;
use Cauce\Services\ExportTokenService;
use Throwable;

/**
 * Ajustes de privacidad: exportar, borrar cuenta y opt-out de analytics.
 */
final class PrivacyController
{
    public function index(): void
    {
        $user = $this->user();

        echo View::render('ajustes/privacidad', [
            'title' => __('privacy.title'),
            'user' => $user,
            'statements' => (new \Cauce\Services\StatementService())->usage($user),
            'errors' => [],
        ], 'layouts/app');
    }

    public function export(): void
    {
        $user = $this->user();

        try {
            (new AnalyticsService())->track('export_generated', [], $user);
            (new ExportService())->stream($user);
        } catch (Throwable $exception) {
            Logger::error('No se pudo generar la exportación', [
                'user_id' => $user['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
            Flash::error('privacy.errors.export_failed');
            redirect('/ajustes/privacidad');
        }
    }

    public function download(): void
    {
        $token = (string) input('token', '');
        $user = AuthService::user();

        if ($user === null) {
            redirect('/login?next=' . rawurlencode('/exportar/descargar?token=' . $token));
        }

        $service = new ExportTokenService();
        $row = $service->resolve($token);

        if ($row === null || (int) $row['user_id'] !== (int) $user['id']) {
            http_response_code(403);
            echo View::render('errors/403', [
                'title' => __('common.errors.403.title'),
                'message' => __('privacy.errors.invalid_export_link'),
            ]);

            return;
        }

        $service->consume((int) $row['id']);
        (new AnalyticsService())->track('export_generated', ['source' => 'arco'], $user);
        (new ExportService())->stream($user);
    }

    public function updateAnalytics(): void
    {
        $user = $this->user();
        $optOut = !empty($_POST['opt_out']);

        try {
            (new AnalyticsService())->setOptOut($user, $optOut);
            AuthService::refresh();
            Flash::success($optOut ? 'privacy.flash.analytics_off' : 'privacy.flash.analytics_on');
        } catch (Throwable $exception) {
            Logger::error('No se pudo actualizar el opt-out de analytics', [
                'user_id' => $user['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
            Flash::error('auth.errors.generic');
        }

        redirect('/ajustes/privacidad');
    }

    public function deleteAccount(): void
    {
        $user = $this->user();
        $password = (string) input('password', '');
        $confirmation = trim((string) input('confirmacion', ''));
        $checked = !empty($_POST['confirmo']);

        if (!AuthService::verifyPassword($user, $password)
            || !hash_equals('ELIMINAR', $confirmation)
            || !$checked
        ) {
            Flash::error('privacy.errors.delete_confirmation');
            redirect('/ajustes/privacidad');
        }

        try {
            (new AccountDeletionService())->delete($user);
        } catch (Throwable $exception) {
            Logger::error('No se pudo eliminar la cuenta', [
                'user_id' => $user['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
            Flash::error('privacy.errors.delete_failed');
            redirect('/ajustes/privacidad');
        }

        AuthService::logout();
        Session::start();
        Flash::success('privacy.flash.deleted');

        redirect('/');
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
