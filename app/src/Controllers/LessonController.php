<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Services\AuthService;
use Cauce\Services\LessonService;

/**
 * Marca una lección contextual como vista.
 */
final class LessonController
{
    public function markSeen(): void
    {
        $user = $this->user();
        $code = trim((string) input('codigo', ''));

        if ($code === '' || !(new LessonService())->markSeenByCode($user, $code)) {
            $this->json(['ok' => false], 404);
        }

        $this->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function user(): array
    {
        $user = AuthService::user();

        if ($user === null) {
            $this->json(['ok' => false], 401);
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
