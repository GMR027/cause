<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\View;
use Cauce\Services\AdminAuditService;
use Cauce\Services\AuthService;
use Cauce\Services\LegalService;
use Throwable;

/**
 * Publicación y versionado de documentos legales.
 */
final class AdminLegalController
{
    public function index(): void
    {
        $service = new LegalService();
        $documents = $service->all();
        $current = $service->current();
        $counts = [];

        foreach ($current as $type => $document) {
            $counts[$type] = $service->acceptedCount($type, (string) $document['version']);
        }

        echo View::render('admin/legal', [
            'title' => __('admin.legal.title'),
            'documents' => $documents,
            'current' => $current,
            'counts' => $counts,
            'errors' => [],
        ], 'layouts/app');
    }

    public function publish(): void
    {
        $user = AuthService::user();

        if ($user === null) {
            redirect('/login');
        }

        try {
            (new LegalService())->publish(
                $user,
                (string) input('tipo', ''),
                (string) input('version', ''),
                (string) input('titulo_es', ''),
                (string) input('titulo_en', ''),
                (string) input('contenido_es', ''),
                (string) input('contenido_en', '')
            );
            AdminAuditService::log('admin.legal.publish', 'legal_documentos', null, [
                'tipo' => (string) input('tipo', ''),
                'version' => (string) input('version', ''),
            ]);
            Flash::success('admin.legal.flash.published');
        } catch (Throwable $exception) {
            Flash::error('admin.legal.errors.publish', [':message' => $exception->getMessage()]);
        }

        redirect('/admin/legal');
    }
}
