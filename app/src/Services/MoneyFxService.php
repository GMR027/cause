<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\IngresoRepository;
use Throwable;

/**
 * Animación del cerdito tras mover dinero.
 *
 * El controlador encola el evento antes de redirigir y el layout lo pinta en
 * la siguiente página: verde al ahorrar, azul al invertir y rojo (roto) en
 * gastos, pagos de deuda y retiros. La intensidad (1-5) crece con el monto.
 */
final class MoneyFxService
{
    public const KINDS = ['save', 'invest', 'out'];
    private const SESSION_KEY = '_money_fx';

    /**
     * Proporción del ingreso del período a partir de la cual sube el nivel.
     */
    private const INCOME_RATIOS = [0.02, 0.05, 0.15, 0.35];

    /**
     * Sin ingreso registrado: umbrales absolutos en centavos de la moneda principal.
     */
    private const ABSOLUTE_CENTS = [50000, 200000, 1000000, 5000000];

    /**
     * @param array<string, mixed> $user
     */
    public static function queue(array $user, string $kind, int $amount, ?string $currency = null): void
    {
        if (!in_array($kind, self::KINDS, true) || $amount <= 0 || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $principal = (string) ($user['moneda_activa'] ?? 'MXN');
        $currency = $currency !== null && trim($currency) !== '' ? strtoupper(trim($currency)) : $principal;
        $principalAmount = $amount;
        $income = 0;

        try {
            if ($currency !== $principal) {
                $principalAmount = (new CurrencyService())->toPrincipal($user, $amount, $currency, gmdate('Y-m-d'))['centavos'];
            }

            $period = (new FinanceSupportService())->period($user);
            $income = (new IngresoRepository())->sumForPeriod((int) $user['id'], $period['inicio'], $period['fin']);
        } catch (Throwable) {
            // Sin tasa o sin período: se usa el monto tal cual y umbrales absolutos.
        }

        try {
            $label = format_money($amount, $currency);
        } catch (Throwable) {
            $label = number_format($amount / 100, 2) . ' ' . $currency;
        }

        $_SESSION[self::SESSION_KEY] = [
            'kind' => $kind,
            'level' => self::level($principalAmount, $income),
            'label' => ($kind === 'out' ? '−' : '+') . $label,
        ];
    }

    /**
     * @return array{kind: string, level: int, label: string}|null
     */
    public static function pull(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION[self::SESSION_KEY])) {
            return null;
        }

        $fx = $_SESSION[self::SESSION_KEY];
        unset($_SESSION[self::SESSION_KEY]);

        if (!is_array($fx) || !in_array($fx['kind'] ?? null, self::KINDS, true)) {
            return null;
        }

        return [
            'kind' => (string) $fx['kind'],
            'level' => max(1, min(5, (int) ($fx['level'] ?? 1))),
            'label' => (string) ($fx['label'] ?? ''),
        ];
    }

    public static function level(int $principalAmount, int $periodIncome): int
    {
        $amount = max(0, $principalAmount);
        $level = 1;

        if ($periodIncome > 0) {
            $ratio = $amount / $periodIncome;

            foreach (self::INCOME_RATIOS as $threshold) {
                if ($ratio >= $threshold) {
                    $level++;
                }
            }

            return $level;
        }

        foreach (self::ABSOLUTE_CENTS as $threshold) {
            if ($amount >= $threshold) {
                $level++;
            }
        }

        return $level;
    }
}
