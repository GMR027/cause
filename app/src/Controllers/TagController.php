<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\View;
use Cauce\Repositories\TagRepository;
use Cauce\Services\AuthService;
use Cauce\Services\TagService;
use InvalidArgumentException;

/**
 * Gestión de tags: renombrar, fusionar y borrar.
 */
final class TagController
{
    public function index(): void
    {
        $user = $this->user();

        echo View::render('ajustes/tags', [
            'title' => __('expenses.tags.title'),
            'tags' => (new TagRepository())->allForUser((int) $user['id']),
        ], 'layouts/app');
    }

    public function store(): void
    {
        $user = $this->user();

        try {
            (new TagService())->create((int) $user['id'], (string) input('nombre', ''));
            Flash::success('expenses.flash.tag_created');
        } catch (InvalidArgumentException) {
            Flash::error('expenses.errors.tag');
        }

        redirect('/ajustes/tags');
    }

    public function rename(): void
    {
        $user = $this->user();

        try {
            (new TagService())->rename(
                (int) ($_POST['id'] ?? 0),
                (int) $user['id'],
                (string) input('nombre', '')
            );
            Flash::success('expenses.flash.tag_renamed');
        } catch (InvalidArgumentException $exception) {
            Flash::error('expenses.errors.tag');
        }

        redirect('/ajustes/tags');
    }

    public function merge(): void
    {
        $user = $this->user();

        try {
            (new TagService())->merge(
                (int) ($_POST['source_id'] ?? 0),
                (int) ($_POST['target_id'] ?? 0),
                (int) $user['id']
            );
            Flash::success('expenses.flash.tag_merged');
        } catch (InvalidArgumentException $exception) {
            Flash::error('expenses.errors.tag');
        }

        redirect('/ajustes/tags');
    }

    public function delete(): void
    {
        $user = $this->user();

        try {
            (new TagService())->delete((int) ($_POST['id'] ?? 0), (int) $user['id']);
            Flash::success('expenses.flash.tag_deleted');
        } catch (InvalidArgumentException) {
            Flash::error('expenses.errors.tag');
        }

        redirect('/ajustes/tags');
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
