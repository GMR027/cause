<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\DonationRepository;
use Cauce\Repositories\GastoRepository;
use Cauce\Repositories\InvestmentRepository;
use Cauce\Repositories\SavingsRepository;
use Cauce\Repositories\SharingRepository;

/**
 * Cálculo compartido del cumplimiento del método por período.
 *
 * Lo usan la gamificación y el score para evitar dos definiciones distintas.
 */
final class MethodComplianceService
{
    /**
     * @param array<string, mixed> $user
     * @param array{inicio: string, fin: string} $period
     * @return array<string, mixed>|null
     */
    public function forPeriod(array $user, array $period): ?array
    {
        $userId = (int) $user['id'];
        $support = new FinanceSupportService();
        $gastos = new GastoRepository();
        $entries = [];
        $seenTypes = [];

        foreach ($support->categories($user) as $category) {
            $type = (string) ($category['tipo'] ?? '');
            $name = (string) ($category['nombre'] ?? '');

            if ($type === 'controlada' || $type === 'no_controlado') {
                $assigned = $gastos->sumAssignedForPart($userId, $name, $period['inicio'], $period['fin']);
                $spent = $gastos->sumSpentForPart($userId, $name, $period['inicio'], $period['fin']);

                if ($assigned <= 0) {
                    continue;
                }

                $entries[] = [
                    'nombre' => $name,
                    'tipo' => 'controlada',
                    'asignado_centavos' => $assigned,
                    'actual_centavos' => $spent,
                    'cumplimiento' => round(GamificationService::compliance(true, $assigned, $spent) * 100, 1),
                ];

                continue;
            }

            if (!in_array($type, ['ahorro', 'inversion', 'donacion', 'compartir'], true) || isset($seenTypes[$type])) {
                continue;
            }

            $seenTypes[$type] = true;
            $assigned = $support->assignedForPeriod($user, [$type], $period['inicio'], $period['fin']);

            if ($assigned <= 0) {
                continue;
            }

            $actual = match ($type) {
                'ahorro' => (new SavingsRepository())->sumPeriodContributions($userId, $period['inicio'], $period['fin']),
                'inversion' => (new InvestmentRepository())->sumContributions(
                    $userId,
                    null,
                    $period['inicio'],
                    $period['fin'],
                    true
                ),
                'donacion' => (new DonationRepository())->sumPeriodNormal(
                    $userId,
                    $period['inicio'],
                    $period['fin']
                ),
                'compartir' => (new SharingRepository())->sumPeriodNormal(
                    $userId,
                    $period['inicio'],
                    $period['fin']
                ),
                default => 0,
            };

            $entries[] = [
                'nombre' => __('gamification.levels.type_' . $type),
                'tipo' => $type,
                'asignado_centavos' => $assigned,
                'actual_centavos' => $actual,
                'cumplimiento' => round(GamificationService::compliance(false, $assigned, $actual) * 100, 1),
            ];
        }

        if ($entries === []) {
            return null;
        }

        return [
            'inicio' => $period['inicio'],
            'fin' => $period['fin'],
            'percent' => round(array_sum(array_column($entries, 'cumplimiento')) / count($entries), 2),
            'categories' => $entries,
        ];
    }

    /**
     * Promedio de los últimos períodos completados.
     *
     * @param array<string, mixed> $user
     * @return array{percent: ?float, periods: list<array<string, mixed>>}
     */
    public function recentAverage(array $user, int $count = 3): array
    {
        $support = new FinanceSupportService();
        $current = $support->period($user);
        $cursor = $current;
        $periods = [];

        for ($i = 0; $i < max(1, $count); $i++) {
            $previous = $support->previousPeriod($user, $cursor);
            $data = $this->forPeriod($user, $previous);

            if ($data !== null) {
                $periods[] = $data;
            }

            $cursor = $previous;
        }

        if ($periods === []) {
            return ['percent' => null, 'periods' => []];
        }

        return [
            'percent' => round(array_sum(array_column($periods, 'percent')) / count($periods), 2),
            'periods' => $periods,
        ];
    }
}
