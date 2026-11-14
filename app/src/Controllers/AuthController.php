<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\Session;
use Cauce\Helpers\View;
use Cauce\Services\AuthService;
use Cauce\Services\LoginThrottleService;

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
            'old' => ['email' => ''],
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
        $next = $this->safeNext((string) input('next', ''));
        $errors = [];
        $throttle = new LoginThrottleService();

        if ($throttle->tooManyAttempts($email)) {
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
                AuthService::login($user);
                Flash::success('auth.flash.logged_in');

                if ($next !== '') {
                    redirect($next);
                }

                redirect((int) ($user['onboarding_completado'] ?? 0) === 1 ? '/inicio' : '/onboarding');
            }
        }

        echo View::render('auth/login', [
            'title' => __('auth.login.title'),
            'old' => ['email' => $email],
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
