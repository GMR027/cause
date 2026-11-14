<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\GastoRepository;
use Cauce\Repositories\MoveRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Movimientos entre categorías: redistribución, no gasto ni retiro.
 */
final class MoveService
{
    public const REASONS = ['emergencia', 'ajuste_prioridades', 'error_asignacion', 'otro'];
    private const WARNING_RATIO = 0.30;

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function panel(array $user): array
    {
        $support = new FinanceSupportService();
        $period = $support->period($user);
        $categories = $this->categoriesWithBalances($user, $period);
        $moves = (new MoveRepository())->forPeriod((int) $user['id'], $period['inicio'], $period['fin']);

        return [
            'period' => $period,
            'categories' => $categories,
            'moves' => $moves,
            'reasons' => self::REASONS,
            'currency' => (string) ($user['moneda_activa'] ?? 'MXN'),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function category(array $user, string $part): array
    {
        $support = new FinanceSupportService();
        $period = $support->period($user);
        $categories = $this->categoriesWithBalances($user, $period);
        $row = null;

        foreach ($categories as $category) {
            if ($category['nombre'] === $part) {
                $row = $category;
                break;
            }
        }

        if ($row === null) {
            throw new RuntimeException('La categoría no existe en tu método.');
        }

        $row['moves'] = (new MoveRepository())->forCategory((int) $user['id'], $part);

        return [
            'period' => $period,
            'category' => $row,
            'categories' => $categories,
            'reasons' => self::REASONS,
            'projection' => (new ProjectionService())->forCategory($user, $part),
            'currency' => (string) ($user['moneda_activa'] ?? 'MXN'),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array{warning: bool, moved: int, available: int, period: array<string, string>}
     */
    public function move(
        array $user,
        string $origin,
        string $destination,
        int $amount,
        string $reason,
        ?string $text,
        string $date
    ): array {
        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero.');
        }

        if ($origin === $destination) {
            throw new InvalidArgumentException('El origen y el destino deben ser distintos.');
        }

        if (!in_array($reason, self::REASONS, true)) {
            throw new InvalidArgumentException('Motivo de movimiento inválido.');
        }

        $support = new FinanceSupportService();
        $period = $support->period($user);
        $categories = $this->categoriesWithBalances($user, $period);
        $originRow = null;
        $destinationRow = null;

        foreach ($categories as $category) {
            if ($category['nombre'] === $origin) {
                $originRow = $category;
            }

            if ($category['nombre'] === $destination) {
                $destinationRow = $category;
            }
        }

        if ($originRow === null || $destinationRow === null) {
            throw new InvalidArgumentException('La categoría no existe en tu método.');
        }

        if ($amount > (int) $originRow['disponible']) {
            throw new RuntimeException('No puedes mover más que el disponible del origen.');
        }

        $utc = $support->toUtc($date, $user);
        $localPeriod = $support->period($user);

        if ($utc < $localPeriod['inicio_dt'] || $utc > $localPeriod['fin_dt']) {
            throw new RuntimeException('Solo puedes mover montos en el período actual.');
        }

        (new MoveRepository())->create(
            (int) $user['id'],
            $period['inicio'],
            $period['fin'],
            $origin,
            $destination,
            $amount,
            (string) ($user['moneda_activa'] ?? 'MXN'),
            $reason,
            $text !== null && trim($text) !== '' ? mb_substr(trim($text), 0, 255) : null,
            $utc->format('Y-m-d H:i:s')
        );

        $original = (int) $originRow['original'];
        $warning = $original > 0 && ($amount / $original) > self::WARNING_RATIO;

        return [
            'warning' => $warning,
            'moved' => $amount,
            'available' => (int) $originRow['disponible'] - $amount,
            'period' => $period,
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param array{inicio: string, fin: string} $period
     * @return list<array<string, mixed>>
     */
    private function categoriesWithBalances(array $user, array $period): array
    {
        $support = new FinanceSupportService();
        $gastos = new GastoRepository();
        $moves = new MoveRepository();
        $userId = (int) $user['id'];
        $result = [];

        foreach ($support->categories($user) as $category) {
            $name = (string) $category['nombre'];
            $original = $gastos->sumOriginalAssignedForPart($userId, $name, $period['inicio'], $period['fin']);
            $in = $moves->sumIn($userId, $name, $period['inicio'], $period['fin']);
            $out = $moves->sumOut($userId, $name, $period['inicio'], $period['fin']);
            $adjusted = $original + $in - $out;
            $spent = $gastos->sumSpentForPart($userId, $name, $period['inicio'], $period['fin']);

            $result[] = [
                'nombre' => $name,
                'tipo' => (string) $category['tipo'],
                'porcentaje' => $category['porcentaje'],
                'original' => $original,
                'entradas' => $in,
                'salidas' => $out,
                'ajustado' => $adjusted,
                'gastado' => $spent,
                'disponible' => max(0, $adjusted - $spent),
            ];
        }

        return $result;
    }
}
