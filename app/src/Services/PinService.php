<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Helpers\PasswordHasher;
use Cauce\Repositories\UserRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * PIN local de desbloqueo.
 *
 * No sustituye la contraseña: solo desbloquea una sesión ya iniciada después
 * de un periodo de inactividad.
 */
final class PinService
{
    private UserRepository $users;

    public function __construct(?UserRepository $users = null)
    {
        $this->users = $users ?? new UserRepository();
    }

    /**
     * @param array<string, mixed> $user
     */
    public function hasPin(array $user): bool
    {
        return trim((string) ($user['pin_hash'] ?? '')) !== '';
    }

    /**
     * Entrar con correo y PIN: solo si la persona lo activó y tiene PIN.
     *
     * @param array<string, mixed> $user
     */
    public function loginEnabled(array $user): bool
    {
        return (int) ($user['pin_login'] ?? 0) === 1 && $this->hasPin($user);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function setLoginEnabled(array $user, bool $enabled): void
    {
        if ($enabled && !$this->hasPin($user)) {
            throw new InvalidArgumentException('Primero crea un PIN.');
        }

        $this->users->setPinLogin((int) $user['id'], $enabled);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function set(array $user, string $pin): void
    {
        $length = (int) config('security.pin.length', 6);

        if (preg_match('/^\d{' . $length . '}$/', $pin) !== 1) {
            throw new InvalidArgumentException('El PIN debe tener ' . $length . ' dígitos.');
        }

        $this->users->setPin((int) $user['id'], PasswordHasher::hash($pin));
    }

    /**
     * @param array<string, mixed> $user
     */
    public function remove(array $user): void
    {
        $this->users->clearPin((int) $user['id']);
    }

    /**
     * @param array<string, mixed> $user
     * @return array{ok: bool, reason: string, remaining: int}
     */
    public function verify(array $user, string $pin): array
    {
        $fresh = $this->users->findById((int) $user['id']);

        if ($fresh === null || trim((string) ($fresh['pin_hash'] ?? '')) === '') {
            return ['ok' => false, 'reason' => 'missing', 'remaining' => 0];
        }

        $lockedUntil = $fresh['pin_locked_until'] ?? null;

        if (is_string($lockedUntil) && $lockedUntil !== ''
            && strtotime($lockedUntil) > time()
        ) {
            return ['ok' => false, 'reason' => 'locked', 'remaining' => 0];
        }

        $length = (int) config('security.pin.length', 6);

        if (preg_match('/^\d{' . $length . '}$/', $pin) !== 1
            || !PasswordHasher::verify($pin, (string) $fresh['pin_hash'])
        ) {
            $attempts = (int) ($fresh['pin_attempts'] ?? 0) + 1;
            $max = (int) config('security.pin.max_attempts', 5);
            $lockUntil = null;

            if ($attempts >= $max) {
                $lockUntil = gmdate('Y-m-d H:i:s', time() + (int) config('security.pin.lock_minutes', 15) * 60);
            }

            $this->users->registerPinFailure((int) $user['id'], $attempts, $lockUntil);

            return [
                'ok' => false,
                'reason' => $lockUntil === null ? 'invalid' : 'locked',
                'remaining' => max(0, $max - $attempts),
            ];
        }

        $this->users->resetPinAttempts((int) $user['id']);

        return ['ok' => true, 'reason' => 'ok', 'remaining' => 0];
    }

    public function lockedUntil(array $user): ?string
    {
        $fresh = $this->users->findById((int) $user['id']);
        $locked = $fresh['pin_locked_until'] ?? null;

        return is_string($locked) && $locked !== '' && strtotime($locked) > time() ? $locked : null;
    }

    public static function remainingLockMinutes(string $lockedUntil): int
    {
        $until = new DateTimeImmutable($lockedUntil, new DateTimeZone('UTC'));
        $seconds = $until->getTimestamp() - time();

        return max(1, (int) ceil($seconds / 60));
    }
}
