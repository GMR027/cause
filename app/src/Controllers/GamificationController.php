<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\View;
use Cauce\Services\AuthService;
use Cauce\Services\GamificationService;
use Throwable;

/**
 * Página de progreso y preferencias de gamificación.
 */
final class GamificationController
{
    private GamificationService $service;

    public function __construct()
    {
        $this->service = new GamificationService();
    }

    public function index(): void
    {
        $user = $this->user();

        echo View::render('progress/index', array_merge(
            $this->service->panel($user),
            ['title' => __('gamification.title')]
        ), 'layouts/app');
    }

    public function dismissChallenge(): void
    {
        $user = $this->user();
        $id = (int) input('id', '0');

        if ($id <= 0) {
            $challenge = $this->service->ensureChallenge($user);
            $id = (int) ($challenge['id'] ?? 0);
        }

        try {
            if ($id > 0) {
                $this->service->dismissChallenge($user, $id);
                Flash::success('gamification.flash.challenge_dismissed');
            }
        } catch (Throwable) {
            Flash::error('gamification.flash.challenge_error');
        }

        redirect(input('origen') === 'inicio' ? '/inicio' : '/logros');
    }

    public function celebrate(): void
    {
        $user = $this->user();
        $id = (int) input('id', '0');

        try {
            if ($id > 0) {
                $this->service->markCelebrated($user, $id);
            }
        } catch (Throwable) {
            // La celebración no debe bloquear la navegación.
        }

        if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch') {
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        redirect(input('origen') === 'logros' ? '/logros' : '/inicio');
    }

    public function toggleGamification(): void
    {
        $user = $this->user();
        $active = (string) input('activo', '0') === '1';

        try {
            $this->service->toggleGamification($user, $active);
            Flash::success($active ? 'gamification.flash.gamification_on' : 'gamification.flash.gamification_off');
        } catch (Throwable) {
            Flash::error('gamification.flash.generic_error');
        }

        redirect('/ajustes/perfil');
    }

    public function toggleSocial(): void
    {
        $user = $this->user();
        $active = (string) input('activo', '0') === '1';

        try {
            $this->service->toggleSocialComparison($user, $active);
            Flash::success($active ? 'gamification.flash.social_on' : 'gamification.flash.social_off');
        } catch (Throwable) {
            Flash::error('gamification.flash.generic_error');
        }

        redirect('/ajustes/perfil');
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
