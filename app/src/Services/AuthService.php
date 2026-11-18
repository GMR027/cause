<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Helpers\Csrf;
use Cauce\Helpers\I18n;
use Cauce\Helpers\Logger;
use Cauce\Helpers\PasswordHasher;
use Cauce\Helpers\Session;
use Cauce\Repositories\SessionRepository;
use Cauce\Repositories\UserRepository;
use Throwable;

/**
 * Autenticación basada en sesiones PHP nativas con seguimiento en BD.
 */
final class AuthService
{
    private static ?array $user = null;

    private static bool $loaded = false;

    private static ?string $lastError = null;

    private const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    private function __construct()
    {
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }

        self::$loaded = true;

        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        try {
            $sessionId = session_id();

            if ($sessionId === '') {
                self::forget();
                return null;
            }

            $session = (new SessionRepository())->find($sessionId);

            if ($session === null || (int) $session['user_id'] !== (int) $_SESSION['user_id']) {
                self::forget();
                return null;
            }

            $idleSeconds = (int) config('security.session.idle_minutes', 720) * 60;
            $absoluteSeconds = (int) config('security.session.absolute_days', 30) * 86400;
            $lastActivity = strtotime((string) ($session['last_activity'] ?? '')) ?: 0;
            $createdAt = strtotime((string) ($session['created_at'] ?? '')) ?: 0;

            if ($lastActivity === 0 || $createdAt === 0
                || time() - $lastActivity > $idleSeconds
                || time() - $createdAt > $absoluteSeconds
            ) {
                (new SessionRepository())->delete($sessionId);
                self::forget();
                Session::set('_auth_notice', 'expired');

                return null;
            }

            $user = (new UserRepository())->findById((int) $_SESSION['user_id']);

            if ($user === null) {
                self::forget();
                return null;
            }

            if ((string) ($user['estado'] ?? 'activo') === 'suspendido') {
                (new SessionRepository())->delete($sessionId);
                self::forget();
                Session::set('_auth_notice', 'suspended');

                return null;
            }

            (new SessionRepository())->touch($sessionId);

            self::$user = $user;

            return $user;
        } catch (Throwable $exception) {
            Logger::error('No se pudo validar la sesión', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user['id'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function attempt(string $email, string $password): ?array
    {
        self::$lastError = null;
        $user = (new UserRepository())->findByEmail($email);

        if ($user === null) {
            password_verify($password, self::DUMMY_HASH);
            self::$lastError = 'invalid';

            return null;
        }

        if (!PasswordHasher::verify($password, (string) $user['password_hash'])) {
            self::$lastError = 'invalid';

            return null;
        }

        if ((string) ($user['estado'] ?? 'activo') === 'suspendido') {
            self::$lastError = 'suspended';

            return null;
        }

        if (PasswordHasher::needsRehash((string) $user['password_hash'])) {
            (new UserRepository())->updatePassword(
                (int) $user['id'],
                PasswordHasher::hash($password),
                (int) ($user['must_change_password'] ?? 0) === 1
            );
            $user = (new UserRepository())->findById((int) $user['id']) ?? $user;
        }

        return $user;
    }

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function verifyPassword(array $user, string $password): bool
    {
        return PasswordHasher::verify($password, (string) ($user['password_hash'] ?? ''));
    }

    public static function refresh(): void
    {
        self::$loaded = false;
        self::$user = null;
    }

    public static function notice(): ?string
    {
        $notice = $_SESSION['_auth_notice'] ?? null;
        unset($_SESSION['_auth_notice']);

        return is_string($notice) ? $notice : null;
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function login(array $user): void
    {
        Session::regenerate();

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_lang'] = (string) $user['idioma'];
        $_SESSION['_last_seen_at'] = time();
        $_SESSION['_unlocked_at'] = time();
        // Bienvenida animada (caja fuerte) en la primera página tras entrar.
        $_SESSION['_login_fx'] = 1;
        unset($_SESSION['_statement_reauth_at'], $_SESSION['_lock_redirect']);

        self::$user = $user;
        self::$loaded = true;

        (new SessionRepository())->create(
            session_id(),
            (int) $user['id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null
        );

        (new UserRepository())->setLastLogin((int) $user['id']);

        Csrf::rotate();
        I18n::set((string) $user['idioma']);

        if (($user['rol'] ?? 'user') === 'admin') {
            AdminAuditService::logFor((int) $user['id'], 'admin.login', 'users', (int) $user['id'], [
                'email' => $user['email'],
            ]);
        }
    }

    public static function logout(): void
    {
        $user = self::user();

        if ($user !== null && ($user['rol'] ?? 'user') === 'admin') {
            AdminAuditService::logFor((int) $user['id'], 'admin.logout', 'users', (int) $user['id']);
        }

        if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
            (new SessionRepository())->delete(session_id());
        }

        self::$user = null;
        self::$loaded = true;

        Session::destroy();
    }

    public static function logoutEverywhere(int $userId): void
    {
        $currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

        (new SessionRepository())->deleteAllForUser($userId);

        if ($currentUserId === $userId) {
            self::$user = null;
            self::$loaded = true;
            Session::destroy();
        }
    }

    private static function forget(): void
    {
        unset($_SESSION['user_id'], $_SESSION['user_lang']);
        self::$user = null;
    }
}
