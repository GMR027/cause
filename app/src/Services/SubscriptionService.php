<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\RecurringExpenseRepository;

/**
 * Análisis de gastos recurrentes y suscripciones.
 */
final class SubscriptionService
{
    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function panel(array $user): array
    {
        $rows = (new RecurringExpenseRepository())->allForUser((int) $user['id'], true);
        $groups = [];

        foreach ($rows as $row) {
            if (!empty($row['deuda_id'])) {
                continue;
            }

            $key = $this->normalize((string) $row['nombre']);
            $factor = self::annualFactor((string) $row['recurrencia']);
            $annual = (int) round((int) ($row['monto_principal_centavos'] ?? $row['monto_centavos']) * $factor);

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'name' => (string) $row['nombre'],
                    'category' => (string) $row['parte_metodo'],
                    'frequency' => (string) $row['recurrencia'],
                    'annual_centavos' => 0,
                    'monthly_centavos' => 0,
                    'count' => 0,
                    'variable' => false,
                    'next' => $row['proxima_ejecucion'] ?? null,
                    'ids' => [],
                ];
            }

            $group = &$groups[$key];
            $group['annual_centavos'] += $annual;
            $group['count']++;
            $group['variable'] = $group['variable'] || (int) $row['monto_variable'] === 1;
            $group['ids'][] = (int) $row['id'];

            if ($group['next'] === null || (string) $row['proxima_ejecucion'] < (string) $group['next']) {
                $group['next'] = $row['proxima_ejecucion'];
            }

            if ($group['frequency'] !== (string) $row['recurrencia']) {
                $group['frequency'] = 'mixta';
            }

            unset($group);
        }

        $subscriptions = array_values($groups);
        $annualTotal = 0;

        foreach ($subscriptions as &$subscription) {
            $subscription['monthly_centavos'] = (int) round($subscription['annual_centavos'] / 12);
            $annualTotal += (int) $subscription['annual_centavos'];
        }
        unset($subscription);

        usort($subscriptions, static fn (array $a, array $b): int => $b['annual_centavos'] <=> $a['annual_centavos']);

        return [
            'subscriptions' => $subscriptions,
            'annual_total_centavos' => $annualTotal,
            'monthly_total_centavos' => (int) round($annualTotal / 12),
            'count' => count($subscriptions),
            'currency' => (string) ($user['moneda_activa'] ?? 'MXN'),
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
