<?php

declare(strict_types=1);

namespace Cauce\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

/**
 * Cálculo del período presupuestal del usuario.
 *
 * Los extremos se calculan en la zona horaria del usuario y se devuelven en
 * UTC, que es como se persisten en ingresos.periodo_inicio / periodo_fin.
 */
final class PeriodoService
{
    private const RECURRENCES = ['diaria', 'semanal', 'mensual', 'anual'];

    /**
     * @param array<string, mixed> $user
     * @return array{inicio: DateTimeImmutable, fin: DateTimeImmutable}
     */
    public function calcularPeriodo(array $user, DateTimeInterface|string $referencia = 'now'): array
    {
        $timezone = $this->userTimezone($user);
        $ref = $this->toLocal($referencia, $timezone);
        $changeBoundary = $this->changeBoundary($user, $timezone);

        $current = $this->normalizeRecurrence((string) ($user['recurrencia'] ?? ''));
        $previous = $this->normalizeRecurrence(
            (string) ($user['recurrencia_anterior'] ?? ''),
            $current
        );

        $usingNew = $changeBoundary === null || $ref >= $changeBoundary;
        $recurrence = $usingNew ? $current : $previous;

        [$start, $end] = $this->range($recurrence, $ref, $user, $timezone);

        // El primer período con la recurrencia nueva no puede empezar antes del
        // cambio: si la nueva regla (p. ej. semanal) arrancó días antes, se recorta.
        if ($usingNew && $changeBoundary !== null && $start < $changeBoundary) {
            $start = $changeBoundary;
        }

        $utc = new DateTimeZone('UTC');

        return [
            'inicio' => $start->setTimezone($utc),
            'fin' => $end->setTimezone($utc),
        ];
    }

    /**
     * Calcula los campos necesarios para cambiar la recurrencia sin romper el
     * período en curso. El cambio aplica desde el siguiente período.
     *
     * @param array<string, mixed> $user
     * @return array{recurrencia: string, recurrencia_anterior: string, recurrencia_cambio_desde: string}
     */
    public function planificarCambioRecurrencia(
        array $user,
        string $nueva,
        DateTimeInterface|string $referencia = 'now'
    ): array {
        $nueva = $this->normalizeRecurrence($nueva);
        $timezone = $this->userTimezone($user);
        $ref = $this->toLocal($referencia, $timezone);

        $current = $this->normalizeRecurrence((string) ($user['recurrencia'] ?? ''));
        $previous = $this->normalizeRecurrence(
            (string) ($user['recurrencia_anterior'] ?? ''),
            $current
        );
        $changeBoundary = $this->changeBoundary($user, $timezone);
        $effective = $changeBoundary === null || $ref >= $changeBoundary ? $current : $previous;

        $period = $this->calcularPeriodo($user, $ref);
        $endLocal = $period['fin']->setTimezone($timezone);
        $nextStart = $endLocal->modify('+1 second')->setTimezone(new DateTimeZone('UTC'));

        return [
            'recurrencia' => $nueva,
            'recurrencia_anterior' => $effective,
            'recurrencia_cambio_desde' => $nextStart->format('Y-m-d H:i:s'),
        ];
    }

    public function userTimezone(array $user): DateTimeZone
    {        $name = (string) ($user['zona_horaria'] ?? '');

        try {
            return new DateTimeZone($name !== '' ? $name : (string) config('app.timezone', 'UTC'));
        } catch (Exception) {
            return new DateTimeZone('UTC');
        }
    }

    public function normalizeRecurrence(string $value, string $fallback = 'mensual'): string
    {
        if (in_array($value, self::RECURRENCES, true)) {
            return $value;
        }

        return in_array($fallback, self::RECURRENCES, true)
            ? $fallback
            : (string) config('periodo.default_recurrence', 'mensual');
    }

    /**
     * Recurrencia vigente en la fecha de referencia, considerando un cambio
     * programado que todavía no entra en vigor.
     *
     * @param array<string, mixed> $user
     */
    public function recurrenciaEfectiva(array $user, DateTimeInterface|string $referencia = 'now'): string
    {
        $timezone = $this->userTimezone($user);
        $ref = $this->toLocal($referencia, $timezone);
        $changeBoundary = $this->changeBoundary($user, $timezone);
        $current = $this->normalizeRecurrence((string) ($user['recurrencia'] ?? ''));
        $previous = $this->normalizeRecurrence(
            (string) ($user['recurrencia_anterior'] ?? ''),
            $current
        );

        return $changeBoundary === null || $ref >= $changeBoundary ? $current : $previous;
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function range(
        string $recurrence,
        DateTimeImmutable $ref,
        array $user,
        DateTimeZone $timezone
    ): array {
        if ($recurrence === 'diaria') {
            $start = $ref->setTime(0, 0, 0);

            return [$start, $start->modify('+1 day -1 second')];
        }

        if ($recurrence === 'semanal') {
            $weekStart = (int) config('periodo.week_starts_on', 1);
            $weekday = (int) $ref->format('N');
            $offset = ($weekday - $weekStart + 7) % 7;
            $start = $ref->modify('-' . $offset . ' days')->setTime(0, 0, 0);

            return [$start, $start->modify('+7 days -1 second')];
        }

        if ($recurrence === 'anual') {
            $start = $ref->setDate((int) $ref->format('Y'), 1, 1)->setTime(0, 0, 0);
            $end = $start->setDate((int) $start->format('Y') + 1, 1, 1)->modify('-1 second');

            return [$start, $end];
        }

        $day = max(1, min(31, (int) ($user['periodo_inicio_dia'] ?? config('periodo.default_start_day', 1))));
        $firstOfMonth = new DateTimeImmutable(
            sprintf('%04d-%02d-01 00:00:00', (int) $ref->format('Y'), (int) $ref->format('n')),
            $timezone
        );
        $startThisMonth = $this->withDay($firstOfMonth, $day);

        if ($ref < $startThisMonth) {
            $start = $this->withDay($firstOfMonth->modify('-1 month'), $day);
        } else {
            $start = $startThisMonth;
        }

        $firstOfNext = (new DateTimeImmutable($start->format('Y-m-01 00:00:00'), $timezone))
            ->modify('+1 month');
        $nextStart = $this->withDay($firstOfNext, $day);

        return [$start, $nextStart->modify('-1 second')];
    }

    private function withDay(DateTimeImmutable $firstOfMonth, int $day): DateTimeImmutable
    {
        $daysInMonth = (int) $firstOfMonth->format('t');
        $safeDay = max(1, min($day, $daysInMonth));

        return $firstOfMonth->setDate(
            (int) $firstOfMonth->format('Y'),
            (int) $firstOfMonth->format('n'),
            $safeDay
        );
    }

    private function toLocal(DateTimeInterface|string $referencia, DateTimeZone $timezone): DateTimeImmutable
    {
        if ($referencia instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($referencia)->setTimezone($timezone);
        }

        try {
            return (new DateTimeImmutable($referencia, $timezone))->setTimezone($timezone);
        } catch (Exception) {
            return new DateTimeImmutable('now', $timezone);
        }
    }

    /**
     * @param array<string, mixed> $user
     */
    private function changeBoundary(array $user, DateTimeZone $timezone): ?DateTimeImmutable
    {
        $value = $user['recurrencia_cambio_desde'] ?? null;

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone($timezone);
        } catch (Exception) {
            return null;
        }
    }
}
