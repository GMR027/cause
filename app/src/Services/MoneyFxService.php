<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\IngresoRepository;
use Throwable;

/**
 * Animación tras mover dinero.
 *
 * El controlador encola el evento antes de redirigir y el layout lo pinta en
 * la siguiente página: verde al ahorrar, gráfica de bolsa al invertir, rojo
 * (roto) en gastos, pagos de deuda y retiros, regalo con estrellas al apuntar
 * un deseo, corazón con manos al compartir, un banco que escupe billetes al
 * registrar un ingreso y una hoja de plan que se escribe y se sella al
 * guardar un planeador. También: tarjeta con candado al registrar una deuda,
 * cerdito que presta monedas a una tarjeta (préstamo de tu ahorro), monedas
 * que saltan entre frascos (traspasos), frasco con bandera al crear una
 * bolsa, meta o portafolio, calendario con campana (recordatorios,
 * recurrentes y suscripciones) y planta que da monedas (rendimientos).
 * La intensidad (1-5) crece con el monto.
 */
final class MoneyFxService
{
    public const KINDS = ['save', 'invest', 'out', 'wish', 'share', 'income', 'plan', 'debt', 'loan', 'transfer', 'new', 'remind', 'yield'];

    /** Tipos que se pueden mostrar sin monto (crear algo, programar un aviso). */
    private const WITHOUT_AMOUNT = ['plan', 'new', 'remind'];
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
     * @param string|null $message clave de moneyfx.* para un mensaje propio de la acción
     */
    public static function queue(array $user, string $kind, int $amount, ?string $currency = null, ?string $message = null): void
    {
        if (!in_array($kind, self::KINDS, true) || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if ($amount <= 0 && !in_array($kind, self::WITHOUT_AMOUNT, true)) {
            return;
        }

        $amount = max(0, $amount);

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
            $label = $amount > 0 ? format_money($amount, $currency) : '';
        } catch (Throwable) {
            $label = number_format($amount / 100, 2) . ' ' . $currency;
        }

        $prefix = match (true) {
            $label === '' => '',
            $kind === 'out' || $kind === 'loan' => '−',
            in_array($kind, ['plan', 'debt', 'transfer', 'new', 'remind'], true) => '',
            default => '+',
        };

        // Un plan no es un movimiento de dinero: su monto suele ser el ingreso
        // completo, así que la proporción siempre daría nivel 5. Para el plan
        // usamos los umbrales absolutos y así la celebración va con su tamaño.
        $level = $kind === 'plan'
            ? self::level($principalAmount, 0)
            : self::level($principalAmount, $income);

        if ($amount === 0) {
            $level = 2;
        }

        $_SESSION[self::SESSION_KEY] = [
            'kind' => $kind,
            'level' => $level,
            'label' => $prefix . $label,
            'message' => $message !== null && preg_match('/^[a-z_]{1,40}$/', $message) === 1 ? $message : null,
        ];
    }

    /**
     * @return array{kind: string, level: int, label: string, message: string|null}|null
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
            'message' => is_string($fx['message'] ?? null) ? $fx['message'] : null,
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
