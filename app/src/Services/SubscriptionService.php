<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\RecurringExpenseRepository;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Suscripciones: registro y seguimiento.
 *
 * Una suscripción es un gasto recurrente (se cobra solo en su fecha) con datos
 * extra de seguimiento: proveedor, fin de la prueba gratis, recordatorio antes
 * del cobro y nota. También se listan los demás recurrentes activos (sin deuda)
 * porque en la práctica también son suscripciones; al editarlos se marcan.
 */
final class SubscriptionService
{
    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function panel(array $user): array
    {
        $repo = new RecurringExpenseRepository();
        $timezone = (new PeriodoService())->userTimezone($user);
        $today = new DateTimeImmutable('today', $timezone);
        $items = [];
        $names = [];

        foreach ($repo->allForUser((int) $user['id'], true) as $row) {
            if (!empty($row['deuda_id'])) {
                continue;
            }

            $item = $this->item($row, $today, $timezone);
            $names[$this->normalize($item['name'])][] = $item['id'];
            $items[] = $item;
        }

        // Dos cargos con el mismo nombre suelen ser un duplicado olvidado.
        foreach ($items as &$item) {
            $item['duplicate'] = count($names[$this->normalize($item['name'])] ?? []) > 1;
        }
        unset($item);

        $annualTotal = array_sum(array_column($items, 'annual_centavos'));

        usort($items, static fn (array $a, array $b): int => [$a['days_to_next'] ?? 9999, $a['name']] <=> [$b['days_to_next'] ?? 9999, $b['name']]);

        $cancelled = [];

        foreach ($repo->cancelledSubscriptions((int) $user['id']) as $row) {
            $item = $this->item($row, $today, $timezone);
            $item['cancelled_at'] = (string) $row['cancelada_at'];
            $cancelled[] = $item;
        }

        return [
            'subscriptions' => $items,
            'cancelled' => $cancelled,
            'annual_total_centavos' => $annualTotal,
            'monthly_total_centavos' => (int) round($annualTotal / 12),
            'cancelled_savings_centavos' => array_sum(array_column($cancelled, 'annual_centavos')),
            'upcoming' => array_values(array_filter($items, static fn (array $item): bool => $item['days_to_next'] !== null && $item['days_to_next'] <= 7)),
            'trials' => array_values(array_filter($items, static fn (array $item): bool => $item['trial_days'] !== null && $item['trial_days'] >= 0)),
            'count' => count($items),
            'currency' => (string) ($user['moneda_activa'] ?? 'MXN'),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function item(array $row, DateTimeImmutable $today, DateTimeZone $timezone): array
    {
        $principal = (int) ($row['monto_principal_centavos'] ?? $row['monto_centavos']);
        $annual = (int) round($principal * self::annualFactor((string) $row['recurrencia']));
        $next = !empty($row['proxima_ejecucion'])
            ? (new DateTimeImmutable((string) $row['proxima_ejecucion'], new DateTimeZone('UTC')))->setTimezone($timezone)
            : null;
        $trial = !empty($row['prueba_hasta']) ? new DateTimeImmutable((string) $row['prueba_hasta'], $timezone) : null;

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['nombre'],
            'provider' => $row['proveedor'] !== null ? (string) $row['proveedor'] : null,
            'category' => (string) $row['parte_metodo'],
            'frequency' => (string) $row['recurrencia'],
            'amount_centavos' => (int) $row['monto_centavos'],
            'currency' => (string) $row['moneda'],
            'monthly_centavos' => (int) round($annual / 12),
            'annual_centavos' => $annual,
            'variable' => (int) ($row['monto_variable'] ?? 0) === 1,
            'next' => $next?->format('Y-m-d'),
            'days_to_next' => $next !== null ? (int) $today->diff($next->setTime(0, 0))->format('%r%a') : null,
            'trial_until' => $trial?->format('Y-m-d'),
            'trial_days' => $trial !== null ? (int) $today->diff($trial)->format('%r%a') : null,
            'reminder_days' => $row['recordatorio_dias'] !== null ? (int) $row['recordatorio_dias'] : null,
            'notes' => $row['notas'] !== null ? (string) $row['notas'] : null,
            'registered' => (int) ($row['es_suscripcion'] ?? 0) === 1,
        ];
    }

    public static function annualFactor(string $frequency): float
    {
        return match ($frequency) {
            'diaria' => 365.0,
            'semanal' => 52.0,
            'anual' => 1.0,
            default => 12.0,
        };
    }

    private function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);

        if (is_string($transliterated) && $transliterated !== '') {
            $name = $transliterated;
        }

        $name = preg_replace('/[^a-z0-9]+/', ' ', $name) ?? $name;

        return trim($name);
    }
}
