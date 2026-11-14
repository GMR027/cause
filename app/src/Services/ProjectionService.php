<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\GastoRepository;
use Cauce\Repositories\IngresoRepository;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Proyección aritmética del período actual.
 */
final class ProjectionService
{
    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function forUser(array $user): array
    {
        $support = new FinanceSupportService();
        $period = $support->period($user);
        $timezone = (new PeriodoService())->userTimezone($user);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $inicio = $period['inicio_dt'];
        $fin = $period['fin_dt'];
        $totalDays = max(1, (int) round(($fin->getTimestamp() - $inicio->getTimestamp()) / 86400));
        $elapsed = min($totalDays, max(1, (int) floor(($now->getTimestamp() - $inicio->getTimestamp()) / 86400) + 1));
        $remaining = max(0, $totalDays - $elapsed);
        $userId = (int) $user['id'];
        $income = (new IngresoRepository())->sumForPeriod($userId, $period['inicio'], $period['fin']);
        $spent = (new GastoRepository())->sumByPeriod($userId, $period['inicio'], $period['fin']);
        $dailyRate = $spent / $elapsed;
        $projectedSpend = (int) round($spent + $dailyRate * $remaining);
        $categories = [];

        foreach ((new GastoService())->disponiblePorParte($user) as $name => $category) {
            if ((string) $category['tipo'] !== 'controlada' || (int) $category['asignado'] <= 0) {
                continue;
            }

            $categorySpent = (int) $category['gastado'];
            $assigned = (int) $category['asignado'];
            $categoryRate = $categorySpent / $elapsed;
            $projected = (int) round($categorySpent + $categoryRate * $remaining);
            $daysToZero = $categoryRate > 0 ? (int) ceil($assigned / $categoryRate) : null;
            $depletionDate = null;

            if ($daysToZero !== null) {
                $depletionDate = $inicio
                    ->setTimezone($timezone)
                    ->modify('+' . max(0, $daysToZero) . ' days')
                    ->format('Y-m-d');
            }

            $categories[] = [
                'nombre' => (string) $name,
                'tipo' => 'controlada',
                'asignado_centavos' => $assigned,
                'gastado_centavos' => $categorySpent,
                'disponible_centavos' => (int) $category['disponible'],
                'ritmo_centavos_dia' => (int) round($categoryRate),
                'proyectado_centavos' => $projected,
                'proyectado_disponible_centavos' => max(0, $assigned - $projected),
                'agotamiento_fecha' => $depletionDate,
                'agotada' => $daysToZero !== null && $daysToZero <= $elapsed,
                'en_riesgo' => $projected > $assigned,
                'exceso_proyectado_centavos' => max(0, $projected - $assigned),
            ];
        }

        usort($categories, static fn (array $a, array $b): int => $b['exceso_proyectado_centavos'] <=> $a['exceso_proyectado_centavos']);
        $atRisk = array_values(array_filter($categories, static fn (array $category): bool => $category['en_riesgo']));

        return [
            'period' => $period,
            'days_total' => $totalDays,
            'days_elapsed' => $elapsed,
            'days_remaining' => $remaining,
            'income_centavos' => $income,
            'spent_centavos' => $spent,
            'projected_spend_centavos' => $projectedSpend,
            'projected_remaining_centavos' => $income - $projectedSpend,
            'has_data' => $spent > 0 || $income > 0,
            'categories' => $categories,
            'at_risk' => array_slice($atRisk, 0, 2),
            'currency' => (string) ($user['moneda_activa'] ?? 'MXN'),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    public function forCategory(array $user, string $name): ?array
    {
        $data = $this->forUser($user);

        foreach ($data['categories'] as $category) {
            if ($category['nombre'] === $name) {
                return $category;
            }
        }

        return null;
    }
}
