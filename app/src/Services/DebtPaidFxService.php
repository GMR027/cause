<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\DebtRepository;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Festejo al liquidar una deuda.
 *
 * El servicio de deudas lo encola cuando el saldo llega a cero (por un pago,
 * un abono al acreditar un ingreso o al editar el saldo) y el layout lo pinta
 * en la siguiente página con assets/js/89-debt-paid.js: la cadena se rompe,
 * cae el sello de «Pagada» y hay fuegos artificiales. Si con esa deuda ya no
 * queda ninguna activa, la fiesta es más grande.
 */
final class DebtPaidFxService
{
    private const SESSION_KEY = '_debt_paid_fx';
    private const MAX_NAMES = 5;

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $debt fila de deudas antes de liquidarse
     */
    public static function queue(array $user, array $debt): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $userId = (int) ($user['id'] ?? 0);
        $debtId = (int) ($debt['id'] ?? 0);
        $repo = new DebtRepository();
        $payments = 0;
        $remaining = 0;

        try {
            $payments = count($repo->paymentsForDebt($debtId, $userId));
            $remaining = $repo->countActive($userId);
        } catch (Throwable) {
            // Sin datos extra el festejo sigue: solo se omiten esas cifras.
        }

        $previous = $_SESSION[self::SESSION_KEY] ?? null;
        $names = is_array($previous) ? (array) ($previous['names'] ?? []) : [];
        $names[] = mb_substr((string) ($debt['nombre'] ?? ''), 0, 120);

        $_SESSION[self::SESSION_KEY] = [
            'names' => array_slice(array_values(array_filter($names, 'is_string')), -self::MAX_NAMES),
            'total_centavos' => max(0, (int) ($debt['monto_total_centavos'] ?? 0)),
            'currency' => (string) ($debt['moneda'] ?? $user['moneda_activa'] ?? 'MXN'),
            'payments' => $payments,
            'days' => self::daysSince((string) ($debt['created_at'] ?? '')),
            'remaining' => $remaining,
        ];
    }

    /**
     * @return array{names: list<string>, total_centavos: int, currency: string, payments: int, days: int, remaining: int}|null
     */
    public static function pull(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION[self::SESSION_KEY])) {
            return null;
        }

        $fx = $_SESSION[self::SESSION_KEY];
        unset($_SESSION[self::SESSION_KEY]);

        if (!is_array($fx) || !is_array($fx['names'] ?? null) || $fx['names'] === []) {
            return null;
        }

        return [
            'names' => array_values(array_map('strval', $fx['names'])),
            'total_centavos' => max(0, (int) ($fx['total_centavos'] ?? 0)),
            'currency' => (string) ($fx['currency'] ?? 'MXN'),
            'payments' => max(0, (int) ($fx['payments'] ?? 0)),
            'days' => max(0, (int) ($fx['days'] ?? 0)),
            'remaining' => max(0, (int) ($fx['remaining'] ?? 0)),
        ];
    }

    private static function daysSince(string $createdAt): int
    {
        if (trim($createdAt) === '') {
            return 0;
        }

        try {
            $start = new DateTimeImmutable($createdAt, new DateTimeZone('UTC'));
            $diff = $start->diff(new DateTimeImmutable('now', new DateTimeZone('UTC')));

            return $diff->invert === 1 ? 0 : (int) $diff->days;
        } catch (Throwable) {
            return 0;
        }
    }
}
