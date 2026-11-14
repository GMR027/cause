<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\View;
use Cauce\Services\AdminAuditService;
use Cauce\Services\LessonService;
use Throwable;

/**
 * Administración de lecciones contextuales.
 */
final class AdminContentController
{
    public function index(): void
    {
        $service = new LessonService();
        $editId = (int) input('editar', '0');
        $editing = $editId > 0 ? $service->find($editId) : null;

        echo View::render('admin/content', [
            'title' => __('lessons.admin.title'),
            'lessons' => $service->adminAll(),
            'editing' => $editing,
            'sections' => LessonService::SECTIONS,
        ], 'layouts/app');
    }

    public function save(): void
    {
        $service = new LessonService();
        $id = (int) input('id', '0');
        $lessonId = $id > 0 ? $id : null;

        try {
            $savedId = $service->save($this->dataFromPost(), $lessonId);
            AdminAuditService::log('admin.lesson.save', 'lecciones', $savedId, [
                'codigo' => (string) input('codigo', ''),
                'publicada' => !empty($_POST['publicada']),
            ]);
            Flash::success('lessons.admin.flash.saved');
        } catch (Throwable $exception) {
            Flash::error('lessons.admin.errors.save', [':message' => $exception->getMessage()]);
            redirect($lessonId !== null ? '/admin/contenido?editar=' . $lessonId : '/admin/contenido');
        }

        redirect('/admin/contenido');
    }

    public function publish(): void
    {
        $id = (int) input('id', '0');
        $published = (string) input('publicada', '0') === '1';

        try {
            (new LessonService())->setPublished($id, $published);
            AdminAuditService::log('admin.lesson.publish', 'lecciones', $id, [
                'publicada' => $published,
            ]);
            Flash::success('lessons.admin.flash.published');
        } catch (Throwable $exception) {
            Flash::error('lessons.admin.errors.publish', [':message' => $exception->getMessage()]);
        }

        redirect('/admin/contenido');
    }

    /**
     * @return array<string, mixed>
     */
    private function dataFromPost(): array
    {
        return [
            'codigo' => (string) input('codigo', ''),
            'seccion' => (string) input('seccion', ''),
            'titulo_es' => (string) input('titulo_es', ''),
            'titulo_en' => (string) input('titulo_en', ''),
            'contenido_es' => (string) input('contenido_es', ''),
            'contenido_en' => (string) input('contenido_en', ''),
            'orden' => (int) input('orden', '0'),
            'publicada' => !empty($_POST['publicada']),
        ];
    }
}
