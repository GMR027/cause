<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\Logger;
use Cauce\Helpers\PasswordHasher;
use Cauce\Repositories\UserRepository;
use Cauce\Services\AuthService;
use Cauce\Services\LoginThrottleService;
use Cauce\Services\PasskeyService;
use Throwable;

/**
 * Entrar, desbloquear y registrar llaves de acceso (huella o rostro).
 *
 * Las opciones se piden por fetch (JSON) y la respuesta del dispositivo se
 * envía en un formulario normal, con su _token, que redirige como siempre.
 */
final class PasskeyController
{
    private PasskeyService $service;

    public function __construct()
    {
        $this->service = new PasskeyService();
    }

    public function loginOptions(): void
    {
        json_response(['ok' => true, 'publicKey' => $this->service->assertionOptions()]);
    }

    public function login(): void
    {
        if (AuthService::check()) {
            redirect('/inicio');
        }

        try {
            $userId = $this->service->verify($this->assertionFromPost());
            $user = (new UserRepository())->findById($userId);

            if ($user === null) {
                throw new \RuntimeException('unknown');
            }

            if ((string) ($user['estado'] ?? 'activo') === 'suspendido') {
                Flash::error('auth.errors.suspended');
                redirect('/login');
            }

            (new LoginThrottleService())->record(strtolower((string) $user['email']), true);
            AuthService::login($user);
            Flash::success('auth.flash.logged_in');
            $next = $this->safeNext((string) input('next', ''));

            if ($next !== '') {
                redirect($next);
            }

            redirect((int) ($user['onboarding_completado'] ?? 0) === 1 ? '/inicio' : '/onboarding');
        } catch (Throwable $exception) {
            $this->fail($exception, null);
            redirect('/login');
        }
    }

    public function unlockOptions(): void
    {
        $user = $this->user();
        json_response(['ok' => true, 'publicKey' => $this->service->assertionOptions((int) $user['id'])]);
    }

    public function unlock(): void
    {
        $user = $this->user();

        try {
            if ($this->service->verify($this->assertionFromPost()) !== (int) $user['id']) {
                throw new \RuntimeException('unknown');
            }

            unset($_SESSION['_locked']);
            $_SESSION['_last_seen_at'] = time();
            $_SESSION['_unlocked_at'] = time();
            $next = $_SESSION['_lock_redirect'] ?? '/inicio';
            unset($_SESSION['_lock_redirect']);

            if (!is_string($next) || !str_starts_with($next, '/') || str_starts_with($next, '//') || str_contains($next, '\\')) {
                $next = '/inicio';
            }

            redirect($next);
        } catch (Throwable $exception) {
            $this->fail($exception, $user);
            redirect('/desbloquear');
        }
    }

    public function registerOptions(): void
    {
        $user = $this->user();
        json_response(['ok' => true, 'publicKey' => $this->service->registrationOptions($user)]);
    }

    public function register(): void
    {
        $user = $this->user();
        $password = (string) input('current_password', '');

        if ($password === '' || !PasswordHasher::verify($password, (string) $user['password_hash'])) {
            unset($_SESSION['_passkey_create']);
            Flash::error('auth.errors.current_password');
            redirect('/ajustes/seguridad');
        }

        try {
            $this->service->register($user, [
                'credential_id' => (string) input('credential_id', ''),
                'client_data' => (string) input('client_data', ''),
                'authenticator_data' => (string) input('authenticator_data', ''),
                'public_key' => (string) input('public_key', ''),
                'algorithm' => (string) input('algorithm', '0'),
                'name' => (string) input('nombre', ''),
            ]);
            Flash::success('auth.passkeys.flash.added');
        } catch (Throwable $exception) {
            $this->fail($exception, $user);
        }

        redirect('/ajustes/seguridad');
    }

    public function delete(): void
    {
        $user = $this->user();

        if ($this->service->delete((int) $user['id'], (int) input('id', '0'))) {
            Flash::success('auth.passkeys.flash.deleted');
        } else {
            Flash::error('auth.passkeys.errors.generic');
        }

        redirect('/ajustes/seguridad');
    }

    /**
     * POST /huella/error — el navegador o la app reportan por qué falló la
     * huella en el dispositivo (antes de llegar al servidor), para poder
     * diagnosticarlo en el log.
     */
    public function clientError(): void
    {
        $clip = static fn (string $key, int $max): string => mb_substr(
            preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) input($key, '')) ?? '',
            0,
            $max
        );

        Logger::warning('Huella: error en el dispositivo', [
            'user_id' => AuthService::id(),
            'mode' => $clip('mode', 10),
            'name' => $clip('name', 60),
            'message' => $clip('message', 300),
            'agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
        ]);

        json_response(['ok' => true]);
    }

    /**
     * @return array<string, string>
     */
    private function assertionFromPost(): array
    {
        return [
            'credential_id' => (string) input('credential_id', ''),
            'client_data' => (string) input('client_data', ''),
            'authenticator_data' => (string) input('authenticator_data', ''),
            'signature' => (string) input('signature', ''),
            'user_handle' => (string) input('user_handle', ''),
        ];
    }

    /**
     * @param array<string, mixed>|null $user
     */
    private function fail(Throwable $exception, ?array $user): void
    {
        $code = $exception->getMessage();
        $known = ['expired', 'unknown', 'invalid', 'origin', 'no_uv', 'cloned', 'unsupported', 'duplicate'];

        if (!in_array($code, $known, true)) {
            Logger::warning('Error con llave de acceso', [
                'user_id' => $user['id'] ?? null,
                'error' => $code,
            ]);
            $code = 'generic';
        }

        Flash::error('auth.passkeys.errors.' . $code);
    }

    private function safeNext(string $next): string
    {
        $next = trim($next);

        if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//')
            || str_contains($next, "\n") || str_contains($next, "\r") || str_contains($next, '\\')
        ) {
            return '';
        }

        return $next;
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
