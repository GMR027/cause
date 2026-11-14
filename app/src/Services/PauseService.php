<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\InvestmentRepository;
use Cauce\Repositories\PauseRepository;
use RuntimeException;

/**
 * Pausas de inversión y acarreo del disponible.
 */
final class PauseService
{
    private const REASONS = ['apuro', 'desempleo', 'otro'];

    private PauseRepository $pauses;

    private InvestmentRepository $investments;

    public function __construct(?PauseRepository $pauses = null, ?InvestmentRepository $investments = null)
    {
        $this->pauses = $pauses ?? new PauseRepository();
        $this->investments = $investments ?? new InvestmentRepository();
    }

    /**
     * @param array<string, mixed> $user
     * @return array{assigned: int, carry: int, normal: int, available: int, paused: bool, pause: ?array<string, mixed>}
     */
    public function status(array $user): array
    {
        $support = new FinanceSupportService();
        $period = $support->period($user);
        $assigned = $support->assignedForType($user, 'inversion');
        $normal = $this->investments->sumContributions(
            (int) $user['id'],
            null,
            $period['inicio'],
            $period['fin'],
            true
        );
        $carry = $this->carryInto($user);
        $pause = $this->pauses->findForPeriod((int) $user['id'], $period['inicio'], $period['fin']);

        return [
            'assigned' => $assigned,
            'carry' => $carry,
            'normal' => $normal,
            'available' => max(0, $assigned + $carry - $normal),
            'paused' => $pause !== null,
            'pause' => $pause,
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    public function carryInto(array $user): int
    {
        $support = new FinanceSupportService();
        $period = $support->period($user);
        $previous = $support->previousPeriod($user, $period);
        $pause = $this->pauses->findForPeriod((int) $user['id'], $previous['inicio'], $previous['fin']);

        return $pause !== null ? (int) ($pause['monto_centavos'] ?? 0) : 0;
    }

    /**
     * @param array<string, mixed> $user
     */
    public function pause(array $user, string $reason, ?string $text): void
    {
        if (!in_array($reason, self::REASONS, true)) {
            throw new RuntimeException('Motivo de pausa inválido.');
        }

        $support = new FinanceSupportService();
        $period = $support->period($user);
        $status = $this->status($user);
        $existing = $this->pauses->findForPeriod((int) $user['id'], $period['inicio'], $period['fin']);
        $note = $text !== null && trim($text) !== '' ? mb_substr(trim($text), 0, 255) : null;

        if ($existing !== null) {
            $this->pauses->update((int) $existing['id'], (int) $user['id'], $status['available'], $reason, $note);

            return;
        }

        $this->pauses->create(
            (int) $user['id'],
            $period['inicio'],
            $period['fin'],
            $status['available'],
            $reason,
            $note
        );
    }

    /**
     * Recalcula el monto de la pausa actual si entraron ingresos después.
     *
     * @param array<string, mixed> $user
     */
    public function refresh(array $user): void
    {
        $support = new FinanceSupportService();
        $period = $support->period($user);
        $pause = $this->pauses->findForPeriod((int) $user['id'], $period['inicio'], $period['fin']);

        if ($pause === null) {
            return;
        }

        $status = $this->status($user);
        $this->pauses->update(
            (int) $pause['id'],
            (int) $user['id'],
            $status['available'],
            (string) $pause['motivo_catalogo'],
            $pause['motivo_texto'] !== null ? (string) $pause['motivo_texto'] : null
        );
    }

    /**
     * @param array<string, mixed> $user
     * @return array{paused: bool, consecutive: bool, pause: ?array<string, mixed>, previous: ?array<string, mixed>}
     */
    public function consecutive(array $user): array
    {
        $support = new FinanceSupportService();
        $period = $support->period($user);
        $previous = $support->previousPeriod($user, $period);
        $current = $this->pauses->findForPeriod((int) $user['id'], $period['inicio'], $period['fin']);
        $before = $this->pauses->findForPeriod((int) $user['id'], $previous['inicio'], $previous['fin']);

        return [
            'paused' => $current !== null,
            'consecutive' => $current !== null && $before !== null,
            'pause' => $current,
            'previous' => $before,
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    public function isPaused(array $user): bool
    {
        $support = new FinanceSupportService();
        $period = $support->period($user);

        return $this->pauses->findForPeriod((int) $user['id'], $period['inicio'], $period['fin']) !== null;
    }
}
