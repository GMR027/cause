<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Logger;
use Cauce\Helpers\PasswordHasher;
use Cauce\Helpers\Session;
use Cauce\Helpers\Validator;
use Cauce\Helpers\View;
use Cauce\Repositories\UserRepository;
use Cauce\Services\AuthService;
use Cauce\Services\LoginThrottleService;
use Cauce\Services\PinService;
use Throwable;

/**
 * Desbloqueo por PIN/contraseña y cambio obligatorio de contraseña.
 */
final class LockController
{
    public function show(): void
    {
        $user = $this->user();
        $pin = new PinService();

        echo View::render('auth/unlock', [
            'title' => __('auth.unlock.title'),
            'hasPin' => $pin->hasPin($user),
            'errors' => [],
        ], 'layouts/auth');
    }

    public function unlock(): void
    {
        $user = $this->user();
        $pin = new PinService();
        $errors = [];
        $pinValue = (string) input('pin', '');
        $password = (string) input('password', '');

        if ($pinValue !== '' && $pin->hasPin($user)) {
            $result = $pin->verify($user, $pinValue);

            if ($result['ok']) {
                $this->markUnlocked();

                return;
            }

            $errors[] = $result['reason'] === 'locked'
                ? __('auth.errors.pin_locked')
                : __('auth.errors.pin_invalid', [':remaining' => (string) $result['remaining']]);
        } elseif ($password !== '') {
            $throttle = new LoginThrottleService();

            if ($throttle->tooManyAttempts((string) $user['email'])) {
                $errors[] = __('auth.errors.too_many_attempts');
            } elseif (AuthService::verifyPassword($user, $password)) {
                $throttle->record((string) $user['email'], true);
                $this->markUnlocked();

                return;
            } else {
                $throttle->record((string) $user['email'], false);
                $errors[] = __('auth.errors.invalid_credentials');
            }
        } else {
            $errors[] = __('auth.errors.unlock_required');
        }

        echo View::render('auth/unlock', [
            'title' => __('auth.unlock.title'),
            'hasPin' => $pin->hasPin($user),
            'errors' => $errors,
        ], 'layouts/auth');
    }

    public function changePasswordForm(): void
    {
        $this->user();

        echo View::render('auth/change-password', [
            'title' => __('auth.change_password.title'),
            'errors' => [],
        ], 'layouts/auth');
    }

    public function changePassword(): void
    {
        $user = $this->user();
        $current = (string) input('current_password', '');
        $new = (string) input('new_password', '');
        $confirmation = (string) input('new_password_confirmation', '');
        $errors = [];

        if (!AuthService::verifyPassword($user, $current)) {
            $errors[] = __('auth.errors.current_password');
        }

        $validator = new Validator([
            'new_password' => $new,
            'new_password_confirmation' => $confirmation,
        ]);
        $validator->password('new_password')->confirmed('new_password', 'new_password_confirmation');

        foreach ($validator->errors() as $error) {
            $errors[] = $error;
        }

        if ($errors === []) {
            try {
                (new UserRepository())->updatePassword(
                    (int) $user['id'],
                    PasswordHasher::hash($new),
                    false
                );
                AuthService::refresh();
                Session::forget('_lock_redirect');
                \Cauce\Helpers\Flash::success('auth.flash.password_changed');
                redirect('/inicio');
            } catch (Throwable $exception) {
                Logger::error('No se pudo cambiar la contraseña', [
                    'user_id' => $user['id'] ?? null,
                    'error' => $exception->getMessage(),
                ]);
                $errors[] = __('auth.errors.generic');
            }
        }

        echo View::render('auth/change-password', [
            'title' => __('auth.change_password.title'),
            'errors' => $errors,
        ], 'layouts/auth');
    }

    private function markUnlocked(): never
    {
        $_SESSION['_last_seen_at'] = time();
        $_SESSION['_unlocked_at'] = time();
        $next = $_SESSION['_lock_redirect'] ?? '/inicio';
        unset($_SESSION['_lock_redirect']);

        if (!is_string($next) || !str_starts_with($next, '/') || str_starts_with($next, '//')
            || str_contains($next, '\\')
        ) {
            $next = '/inicio';
        }

        redirect($next);
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
