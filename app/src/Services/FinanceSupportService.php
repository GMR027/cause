<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\GastoRepository;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Utilidades compartidas por los paneles de ahorro, inversión y donación.
 */
final class FinanceSupportService
{
    /**
     * @param array<string, mixed> $user
     * @return array{inicio: string, fin: string, inicio_dt: DateTimeImmutable, fin_dt: DateTimeImmutable}
     */
    public function period(array $user): array
    {
        $period = (new PeriodoService())->calcularPeriodo($user);

        return [
            'inicio' => $period['inicio']->format('Y-m-d H:i:s'),
            'fin' => $period['fin']->format('Y-m-d H:i:s'),
            'inicio_dt' => $period['inicio'],
            'fin_dt' => $period['fin'],
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param array{inicio_dt: DateTimeImmutable, fin_dt: DateTimeImmutable} $period
     * @return array{inicio: string, fin: string, inicio_dt: DateTimeImmutable, fin_dt: DateTimeImmutable}
     */
    public function previousPeriod(array $user, array $period): array
    {
        $reference = $period['inicio_dt']->modify('-1 day');
        $previous = (new PeriodoService())->calcularPeriodo($user, $reference);

        return [
            'inicio' => $previous['inicio']->format('Y-m-d H:i:s'),
            'fin' => $previous['fin']->format('Y-m-d H:i:s'),
            'inicio_dt' => $previous['inicio'],
            'fin_dt' => $previous['fin'],
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array{active: array<string, mixed>, method: array<string, mixed>, percentages: array<string, float>}|null
     */
    public function activeData(array $user): ?array
    {
        return (new MethodService())->activeData($user);
    }

    /**
     * @param array<string, mixed> $user
     * @return list<array{nombre: string, porcentaje: ?int, tipo: string, orden: int}>
     */
    public function categories(array $user): array
    {
        $data = $this->activeData($user);

        return $data !== null ? $data['method']['categorias'] : [];
    }

    /**
     * @param array<string, mixed> $user
     * @return list<string>
     */
    public function categoryNamesByType(array $user, string $type): array
    {
        $names = [];

        foreach ($this->categories($user) as $category) {
            if ((string) $category['tipo'] === $type) {
                $names[] = (string) $category['nombre'];
            }
        }

        return $names;
    }

    /**
     * @param array<string, mixed> $user
     */
    public function assignedForType(array $user, string $type): int
    {
        $period = $this->period($user);

        return $this->assignedForPeriod($user, [$type], $period['inicio'], $period['fin']);
    }

    /**
     * @param array<string, mixed> $user
     * @param list<string> $types
     */
    public function assignedForPeriod(array $user, array $types, string $inicio, string $fin): int
    {
        $categories = [];

        foreach ($this->categories($user) as $category) {
            if (in_array((string) $category['tipo'], $types, true)) {
                $categories[] = (string) $category['nombre'];
            }
        }

        if ($categories === []) {
            return 0;
        }

        return (new GastoRepository())->sumAssignedForCategories(
            (int) $user['id'],
            $categories,
            $inicio,
            $fin
        );
    }

    /**
     * @param array<string, mixed> $user
     */
    public function ago(array $user): DateTimeZone
    {
        return (new PeriodoService())->userTimezone($user);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function toUtc(string $date, array $user): DateTimeImmutable
    {
        $value = trim($date);

        if ($value === '' || $value === 'now') {
            return new DateTimeImmutable('now', $this->ago($user));
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) === 1) {
            if (!checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
                throw new \InvalidArgumentException('La fecha no es válida.');
            }

            $value .= ' 12:00:00';
        }

        return (new DateTimeImmutable($value, $this->ago($user)))->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * @param array<string, mixed> $user
     */
    public function isCurrentPeriodDate(string $utcDate, array $user): bool
    {
        $period = $this->period($user);
        $date = new DateTimeImmutable($utcDate, new DateTimeZone('UTC'));

        return $date >= $period['inicio_dt'] && $date <= $period['fin_dt'];
    }
}
