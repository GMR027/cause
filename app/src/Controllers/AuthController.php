<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\Session;
use Cauce\Helpers\View;
use Cauce\Services\AuthService;
use Cauce\Repositories\UserRepository;
use Cauce\Services\LoginThrottleService;
use Cauce\Services\PinService;

final class AuthController
{
    public function showLogin(): void
    {
        if (AuthService::check()) {
            redirect('/inicio');
        }

        $notice = AuthService::notice();

        echo View::render('auth/login', [
            'title' => __('auth.login.title'),
            'old' => ['email' => '', 'metodo' => 'password'],
            'errors' => $notice === null ? [] : [__('auth.errors.' . $notice)],
            'next' => $this->safeNext((string) input('next', '')),
        ], 'layouts/auth');
    }

    public function login(): void
    {
        if (AuthService::check()) {
            redirect('/inicio');
        }

        $email = strtolower((string) input('email', ''));
        $password = (string) input('password', '');
        $method = (string) input('metodo', 'password') === 'pin' ? 'pin' : 'password';
        $next = $this->safeNext((string) input('next', ''));
        $errors = [];
        $throttle = new LoginThrottleService();

        if ($method === 'pin' && !$throttle->tooManyAttempts($email)) {
            $user = $this->attemptPin($email, (string) input('pin', ''), $errors);

            if ($user !== null) {
                $throttle->record($email, true);
                $this->finishLogin($user, $next);
            }

            $throttle->record($email, false);
        } elseif ($throttle->tooManyAttempts($email)) {
            $errors[] = __('auth.errors.too_many_attempts');
        } elseif ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || $password === '') {
            $errors[] = __('auth.errors.invalid_credentials');
        } else {
            $user = AuthService::attempt($email, $password);

            if ($user === null) {
                $throttle->record($email, false);
                $errors[] = AuthService::lastError() === 'suspended'
                    ? __('auth.errors.suspended')
                    : __('auth.errors.invalid_credentials');
            } else {
                $throttle->record($email, true);
                $this->finishLogin($user, $next);
            }
        }

        echo View::render('auth/login', [
            'title' => __('auth.login.title'),
            'old' => ['email' => $email, 'metodo' => $method],
            'errors' => $errors,
            'next' => $next,
        ], 'layouts/auth');
    }

    public function logout(): void
    {
        AuthService::logout();

        Session::start();
        Flash::success('auth.flash.logged_out');

        redirect('/login');
    }

    /**
     * Correo + PIN, solo si la persona activó esta opción en Ajustes.
     * El error es el mismo exista o no la cuenta, salvo el bloqueo del PIN.
     *
     * @param list<string> $errors
     * @return array<string, mixed>|null
     */
    private function attemptPin(string $email, string $pin, array &$errors): ?array
    {
        $generic = __('auth.errors.pin_login_invalid');
        $service = new PinService();
        $user = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            ? (new UserRepository())->findByEmail($email)
            : null;

        if ($user === null || !$service->loginEnabled($user) || $pin === '') {
            // Mismo costo que verificar un PIN real.
            password_verify($pin, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
            $errors[] = $generic;

            return null;
        }

        $result = $service->verify($user, $pin);

        if (!$result['ok']) {
            $errors[] = $result['reason'] === 'locked' ? __('auth.errors.pin_login_locked') : $generic;

            return null;
        }

        if ((string) ($user['estado'] ?? 'activo') === 'suspendido') {
            $errors[] = __('auth.errors.suspended');

            return null;
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function finishLogin(array $user, string $next): never
    {
        AuthService::login($user);
        Flash::success('auth.flash.logged_in');

        if ($next !== '') {
            redirect($next);
        }

        redirect((int) ($user['onboarding_completado'] ?? 0) === 1 ? '/inicio' : '/onboarding');
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
}
