<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\GastoRepository;
use Cauce\Repositories\IngresoRepository;
use Cauce\Repositories\MetodoRepository;
use Cauce\Repositories\NoControladoRepository;
use Cauce\Repositories\UsuarioMetodoRepository;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Detección de inconsistencias de Nivel 1 para Inicio.
 */
final class InsightService
{
    /**
     * @param array<string, mixed> $user
     * @return list<array{key: string, params: array<string, string>, route: ?string, action: ?string, severity: string}>
     */
    public function forUser(array $user): array
    {
        $periods = new PeriodoService();
        $current = $periods->calcularPeriodo($user);
        $previous = $periods->calcularPeriodo($user, $current['inicio']->modify('-1 day'));
        $previous2 = $periods->calcularPeriodo($user, $previous['inicio']->modify('-1 day'));

        $incomes = new IngresoRepository();
        $gastos = new GastoRepository();
        $noControlado = new NoControladoRepository();
        $userId = (int) $user['id'];

        $curr = $this->strings($current);
        $prev = $this->strings($previous);
        $prev2 = $this->strings($previous2);

        $incomeNow = $incomes->sumForPeriod($userId, $curr[0], $curr[1]);
        $incomePrev = $incomes->sumForPeriod($userId, $prev[0], $prev[1]);
        $expenseNow = $gastos->sumByPeriod($userId, $curr[0], $curr[1]);
        $expensePrev = $gastos->sumByPeriod($userId, $prev[0], $prev[1]);

        $insights = [];

        if ($incomeNow > 0 && $incomePrev > 0 && $expenseNow > $incomeNow && $expensePrev > $incomePrev) {
            $insights[] = [
                'key' => 'expenses.insights.expense_gt_income',
                'params' => [],
                'route' => '/ahorro',
                'action' => 'expenses.insights.see_savings',
                'severity' => 'warning',
            ];
        }

        $noControladoNow = $noControlado->sumByPeriod($userId, $curr[0], $curr[1]);
        $noControladoPrev = $noControlado->sumByPeriod($userId, $prev[0], $prev[1]);

        if ($noControladoNow > 0 && $noControladoNow > $noControladoPrev) {
            $insights[] = [
                'key' => 'expenses.insights.uncontrolled_growing',
                'params' => [],
                'route' => '/ajustes/ingreso',
                'action' => 'expenses.insights.review_method',
                'severity' => 'info',
            ];
        }

        $active = (new UsuarioMetodoRepository())->activeForUser($userId);
        $method = $active !== null ? (new MetodoRepository())->findById((int) $active['metodo_id']) : null;

        if ($method !== null) {
            foreach ($method['categorias'] as $category) {
                $name = (string) $category['nombre'];
                $assigned = $gastos->sumAssignedForPart($userId, $name, $curr[0], $curr[1]);
                $spent = $gastos->sumByPartPeriod($userId, $name, $curr[0], $curr[1]);

                if ($assigned > 0 && $spent >= $assigned) {
                    $insights[] = [
                        'key' => 'expenses.insights.category_full',
                        'params' => [':category' => $name],
                        'route' => '/ajustes/ingreso',
                        'action' => 'expenses.insights.adjust_percentages',
                        'severity' => 'warning',
                    ];
                    break;
                }
            }

            foreach ($method['categorias'] as $category) {
                if (!in_array((string) $category['tipo'], ['ahorro', 'inversion'], true)) {
                    continue;
                }

                $name = (string) $category['nombre'];
                $assigned = $gastos->sumAssignedForPart($userId, $name, $curr[0], $curr[1]);

                if ($assigned <= 0) {
                    continue;
                }

                $spentCurrent = $gastos->sumByPartPeriod($userId, $name, $curr[0], $curr[1]);
                $spentPrevious = $gastos->sumByPartPeriod($userId, $name, $prev[0], $prev[1]);
                $spentPrevious2 = $gastos->sumByPartPeriod($userId, $name, $prev2[0], $prev2[1]);

                if ($spentCurrent === 0 && $spentPrevious === 0 && $spentPrevious2 === 0) {
                    $insights[] = [
                        'key' => 'expenses.insights.savings_untouched',
                        'params' => [':category' => $name],
                        'route' => '/ahorro',
                        'action' => 'expenses.insights.review_savings',
                        'severity' => 'info',
                    ];
                    break;
                }
            }
        }

        return array_slice($insights, 0, 2);
    }

    /**
     * @param array{inicio: DateTimeImmutable, fin: DateTimeImmutable} $period
     * @return array{0: string, 1: string}
     */
    private function strings(array $period): array
    {
        $utc = new DateTimeZone('UTC');

        return [
            $period['inicio']->setTimezone($utc)->format('Y-m-d H:i:s'),
            $period['fin']->setTimezone($utc)->format('Y-m-d H:i:s'),
        ];
    }
}
