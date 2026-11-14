<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Helpers\PasswordHasher;
use Cauce\Repositories\RecoveryRepository;
use Cauce\Repositories\SessionRepository;
use Cauce\Repositories\UserRepository;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Registro de usuarios, códigos de recuperación y restablecimiento.
 */
final class RecoveryService
{
    private RecoveryRepository $repository;

    public function __construct(?RecoveryRepository $repository = null)
    {
        $this->repository = $repository ?? new RecoveryRepository();
    }

    public function generateCode(): string
    {
        $alphabet = (string) config('auth.recovery.alphabet', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789');
        $length = (int) config('auth.recovery.code_length', 16);
        $max = strlen($alphabet) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }

    public function formatCode(string $code): string
    {
        return rtrim(chunk_split($code, 4, '-'), '-');
    }

    public function normalizeCode(string $code): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', strtoupper($code));
    }

    public function hashCode(string $code): string
    {
        return hash('sha256', $this->normalizeCode($code));
    }

    public function createCodeForUser(int $userId): string
    {
        $plain = $this->generateCode();
        $this->repository->createCode($userId, $this->hashCode($plain));

        return $plain;
    }

    public function verifyCode(int $userId, string $code): ?int
    {
        $normalized = $this->normalizeCode($code);

        if (strlen($normalized) !== (int) config('auth.recovery.code_length', 16)) {
            return null;
        }

        $row = $this->repository->findCode($userId, $this->hashCode($normalized));

        return $row === null ? null : (int) $row['id'];
    }

    public function markCodeUsed(int $codeId): void
    {
        $this->repository->markCodeUsed($codeId);
    }

    public function normalizeAnswer(string $answer): string
    {
        $normalized = trim($answer);

        if (function_exists('mb_strtolower')) {
            $normalized = mb_strtolower($normalized, 'UTF-8');
        } else {
            $normalized = strtolower($normalized);
        }

        $normalized = (string) preg_replace('/\s+/u', ' ', $normalized);

        if (function_exists('iconv')) {
            $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);

            if (is_string($transliterated) && $transliterated !== '') {
                $normalized = $transliterated;
            }
        }

        return $normalized;
    }

    public function hashAnswer(string $answer): string
    {
        return PasswordHasher::hash($this->normalizeAnswer($answer));
    }

    public function verifyAnswer(string $answer, string $hash): bool
    {
        return PasswordHasher::verify($this->normalizeAnswer($answer), $hash);
    }

    /**
     * @param list<array<string, mixed>> $questions
     * @param list<string>               $answers
     */
    public function verifyQuestions(array $questions, array $answers): bool
    {
        $answers = array_values($answers);

        if (count($questions) !== 3 || count($answers) !== 3) {
            return false;
        }

        foreach ($questions as $index => $question) {
            $answer = trim((string) ($answers[$index] ?? ''));

            if ($answer === '' || !$this->verifyAnswer($answer, (string) $question['respuesta_hash'])) {
                return false;
            }
        }

        return true;
    }

    public function recordAttempt(int $userId, bool $success): void
    {
        $this->repository->logAttempt($userId, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $success);
    }

    /**
     * @return array{locked: bool, minutes: int, attempts: int}
     */
    public function lockStatus(int $userId): array
    {
        $maxAttempts = (int) config('auth.recovery.max_attempts', 3);
        $lockMinutes = (int) config('auth.recovery.lock_minutes', 15);
        $attempts = $this->repository->failedAttemptsSinceLastSuccess($userId, $lockMinutes);

        if ($attempts < $maxAttempts) {
            return ['locked' => false, 'minutes' => 0, 'attempts' => $attempts];
        }

        $lastFailure = $this->repository->lastFailureAt($userId);

        if ($lastFailure === null) {
            return ['locked' => false, 'minutes' => 0, 'attempts' => $attempts];
        }

        $last = new DateTimeImmutable($lastFailure, new DateTimeZone('UTC'));
        $remaining = ($lockMinutes * 60) - (time() - $last->getTimestamp());
        $minutes = max(0, (int) ceil($remaining / 60));

        return [
            'locked' => $minutes > 0,
            'minutes' => $minutes,
            'attempts' => $attempts,
        ];
    }

    public function resetPassword(int $userId, string $password, ?int $codeId = null): void
    {
        $db = \Cauce\Config\Database::connection();

        try {
            $db->beginTransaction();

            (new UserRepository($db))->updatePassword($userId, PasswordHasher::hash($password));

            if ($codeId !== null) {
                (new RecoveryRepository($db))->markCodeUsed($codeId);
            }

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        (new SessionRepository($db))->deleteAllForUser($userId);
    }
}
