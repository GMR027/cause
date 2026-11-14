<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\View;
use Cauce\Repositories\SummaryRepository;
use Cauce\Services\AuthService;
use Cauce\Services\SummaryService;
use Throwable;

/**
 * Resúmenes mensuales inmutables y reflexión editable.
 */
final class SummaryController
{
    public function index(): void
    {
        $user = $this->user();
        $repo = new SummaryRepository();
        $id = (int) input('id', '0');

        if ($id > 0) {
            $summary = $repo->findForUser($id, (int) $user['id']);

            if ($summary === null) {
                Flash::error('summary.flash.not_found');
                redirect('/resumen');
            }

            $repo->markRead($id, (int) $user['id']);
            $content = json_decode((string) $summary['contenido_json'], true);

            echo View::render('summary/show', [
                'title' => __('summary.title'),
                'summary' => $summary,
                'content' => is_array($content) ? $content : [],
            ], 'layouts/app');

            return;
        }

        echo View::render('summary/index', [
            'title' => __('summary.title'),
            'summaries' => $repo->listForUser((int) $user['id']),
        ], 'layouts/app');
    }

    public function reflection(): void
    {
        $user = $this->user();
        $id = (int) input('id', '0');
        $repo = new SummaryRepository();
        $summary = $repo->findForUser($id, (int) $user['id']);

        if ($summary === null) {
            Flash::error('summary.flash.not_found');
            redirect('/resumen');
        }

        $text = trim((string) input('reflexion', ''));

        if (mb_strlen($text) > 4000) {
            $text = mb_substr($text, 0, 4000);
        }

        try {
            $repo->updateReflection(
                $id,
                (int) $user['id'],
                $text !== '' ? $text : null
            );
            Flash::success('summary.flash.reflection_saved');
        } catch (Throwable) {
            Flash::error('summary.flash.error');
        }

        redirect('/resumen?id=' . $id);
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
