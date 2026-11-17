<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Services\AuthService;
use Cauce\Services\OnboardingService;

/**
 * Endpoints JSON mínimos de la Fase 2.
 */
final class ApiController
{
    public function appVersion(): void
    {
        $scheme = \Cauce\Helpers\Client::isHttps() ? 'https' : 'http';
        json_response((new \Cauce\Services\AndroidAppService())->publicInfo($scheme . '://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')));
    }

    public function onboardingState(): void
    {
        $user = AuthService::user();

        if ($user === null) {
            json_response(['ok' => false, 'error' => 'unauthenticated'], 401);
        }

        $patch = [];

        if (isset($_POST['monto_centavos'])) {
            $patch['monto_centavos'] = (int) $_POST['monto_centavos'];
        }

        if (isset($_POST['metodo_preview']) && is_string($_POST['metodo_preview'])) {
            $patch['metodo_preview'] = $_POST['metodo_preview'];
        }

        if (isset($_POST['paso']) && is_string($_POST['paso'])) {
            $patch['paso'] = $_POST['paso'];
        }

        $state = (new OnboardingService())->save($user, $patch);

        json_response(['ok' => true, 'state' => $state]);
    }
}
