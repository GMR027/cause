<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Services\AuthService;
use Cauce\Services\CoverService;
use RuntimeException;
use Throwable;

/**
 * Subir, quitar y servir portadas de tarjetas.
 */
final class CoverController
{
    private CoverService $service;

    public function __construct()
    {
        $this->service = new CoverService();
    }

    public function update(): void
    {
        $user = $this->user();
        $target = (string) input('tipo', '');
        $id = (int) input('id', '0');
        $return = CoverService::validTarget($target) ? CoverService::returnPath($target) : '/inicio';

        // Si la subida excede post_max_size, PHP vacía $_POST y $_FILES.
        if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            Flash::error('covers.errors.too_big');
            redirect($return);
        }

        try {
            if (!empty($_POST['quitar'])) {
                $this->service->remove((int) $user['id'], $target, $id);
                Flash::success('covers.flash.removed');
            } else {
                $file = $_FILES['portada'] ?? [];
                $this->service->upload((int) $user['id'], $target, $id, is_array($file) && !is_array($file['tmp_name'] ?? null) ? $file : []);
                Flash::success('covers.flash.saved');
            }
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
            Flash::error(str_starts_with($message, 'covers.errors.') ? $message : 'covers.errors.upload');
        } catch (Throwable) {
            Flash::error('covers.errors.upload');
        }

        redirect($return);
    }

    public function show(): void
    {
        $user = $this->user();
        $target = isset($_GET['tipo']) && is_string($_GET['tipo']) ? $_GET['tipo'] : '';
        $id = isset($_GET['id']) && is_string($_GET['id']) ? (int) $_GET['id'] : 0;
        $path = $this->service->pathFor((int) $user['id'], $target, $id);

        if ($path === null) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: image/jpeg');
        header('Cache-Control: private, max-age=2592000');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
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
