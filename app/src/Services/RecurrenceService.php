<?php

declare(strict_types=1);

namespace Cauce\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Fechas de ejecución de gastos recurrentes.
 */
final class RecurrenceService
{
    public function next(DateTimeInterface $from, string $recurrence, DateTimeZone $timezone): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromInterface($from)->setTimezone($timezone);

        return match ($recurrence) {
            'diaria' => $date->modify('+1 day'),
            'semanal' => $date->modify('+7 days'),
            'anual' => $this->nextYear($date),
            default => $this->nextMonth($date),
        };
    }

    /**
     * Ocurrencias vencidas hasta $now, en orden.
     *
     * @return list<DateTimeImmutable>
     */
    public function dueDates(
        DateTimeInterface $nextUtc,
        DateTimeInterface $nowUtc,
        string $recurrence,
        DateTimeZone $timezone,
        int $max = 24
    ): array {
        $cursor = DateTimeImmutable::createFromInterface($nextUtc)->setTimezone($timezone);
        $limit = DateTimeImmutable::createFromInterface($nowUtc)->setTimezone($timezone);
        $dates = [];

        while ($cursor <= $limit && count($dates) < $max) {
            $dates[] = $cursor;
            $cursor = $this->next($cursor, $recurrence, $timezone);
        }

        return $dates;
    }

    private function nextMonth(DateTimeImmutable $date): DateTimeImmutable
    {
        $day = (int) $date->format('j');
        $firstOfNext = (new DateTimeImmutable($date->format('Y-m-01 00:00:00'), $date->getTimezone()))
            ->modify('+1 month');
        $safeDay = min($day, (int) $firstOfNext->format('t'));

        return $firstOfNext
            ->setDate((int) $firstOfNext->format('Y'), (int) $firstOfNext->format('n'), $safeDay)
            ->setTime((int) $date->format('H'), (int) $date->format('i'), (int) $date->format('s'));
    }

    private function nextYear(DateTimeImmutable $date): DateTimeImmutable
    {
        $year = (int) $date->format('Y') + 1;
        $month = (int) $date->format('n');
        $day = (int) $date->format('j');
        $first = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $date->getTimezone());
        $safeDay = min($day, (int) $first->format('t'));

        return $first
            ->setDate($year, $month, $safeDay)
            ->setTime((int) $date->format('H'), (int) $date->format('i'), (int) $date->format('s'));
    }
}
