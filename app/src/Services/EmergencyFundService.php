<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\GastoRepository;
use Cauce\Repositories\SavingsRepository;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Fondo de emergencia: 3 meses de gasto promedio.
 */
final class EmergencyFundService
{
    /**
     * @param array<string, mixed> $user
     * @return array{total: int, periods: int, average: int}
     */
    public function averageExpenses(array $user): array
    {
        $timezone = (new FinanceSupportService())->ago($user);
        $now = new DateTimeImmutable('now', $timezone);
        $currentMonthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $startLocal = $currentMonthStart->modify('-3 months');
        $endLocal = $currentMonthStart->modify('-1 second');
        $utc = new DateTimeZone('UTC');
        $gastos = new GastoRepository();
        $total = $gastos->sumBetween(
            (int) $user['id'],
            $startLocal->setTimezone($utc)->format('Y-m-d H:i:s'),
            $endLocal->setTimezone($utc)->format('Y-m-d H:i:s')
        );
        $count = 3;

        if ($total === 0) {
            $period = (new FinanceSupportService())->period($user);
            $current = $gastos->sumByPeriod((int) $user['id'], $period['inicio'], $period['fin']);

            if ($current > 0) {
                $total = $current;
                $count = 1;
            }
        }

        return [
            'total' => $total,
            'periods' => $count,
            'average' => $count > 0 ? intdiv($total, $count) : 0,
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    public function suggestedTarget(array $user): int
    {
        return 3 * $this->averageExpenses($user)['average'];
    }

    /**
     * @param array<string, mixed> $user
     * @return array{meta: array<string, mixed>, current: int, suggested: int, notify: bool, change_percent: float}
     */
    public function ensure(array $user): array
    {
        $repo = new SavingsRepository();
        $suggested = $this->suggestedTarget($user);
        $currency = (string) ($user['moneda_activa'] ?? 'MXN');
        $default = $repo->ensureDefaultAccount((int) $user['id'], $currency);
        $meta = $repo->ensureEmergencyMeta(
            (int) $user['id'],
            $suggested,
            $currency,
            (int) ($default['id'] ?? 0)
        );
        $current = (int) ($meta['objetivo_centavos'] ?? 0);

        if ($current === 0 && $suggested > 0) {
            $repo->updateEmergencyObjective((int) $meta['id'], (int) $user['id'], $suggested);
            $current = $suggested;
            $meta['objetivo_centavos'] = $suggested;
        }

        if (($meta['cuenta_id'] ?? null) === null && (int) ($default['id'] ?? 0) > 0) {
            $repo->updateMetaAccount((int) $meta['id'], (int) $user['id'], (int) $default['id']);
            $meta['cuenta_id'] = (int) $default['id'];
        }

        $change = $current > 0 ? (abs($suggested - $current) / $current) * 100 : 0.0;

        return [
            'meta' => $meta,
            'current' => $current,
            'suggested' => $suggested,
            'notify' => $current > 0 && $suggested > 0 && $change > 20.0,
            'change_percent' => round($change, 1),
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    public function update(array $user, int $metaId): void
    {
        $repo = new SavingsRepository();
        $meta = $repo->findMeta($metaId, (int) $user['id']);

        if ($meta === null || (int) $meta['es_emergencia'] !== 1) {
            throw new \RuntimeException('El fondo de emergencia no existe.');
        }

        $repo->updateEmergencyObjective($metaId, (int) $user['id'], $this->suggestedTarget($user));
    }
}
