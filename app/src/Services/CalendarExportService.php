<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\RecurringExpenseRepository;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Exportación de gastos recurrentes a un calendario .ics.
 *
 * No hay OAuth ni sincronización: solo se genera un archivo descargable.
 */
final class CalendarExportService
{
    public const FILENAME = 'cauce-recordatorios.ics';

    /**
     * @param array<string, mixed> $user
     */
    public function forUser(array $user): string
    {
        $rows = (new RecurringExpenseRepository())->allForUser((int) $user['id'], true);

        return $this->build($rows, (string) ($user['moneda_activa'] ?? 'MXN'));
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function build(array $rows, string $currency = 'MXN'): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Cauce//Recordatorios//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escape(__('calendar.ics.calendar_name')),
        ];

        foreach ($rows as $row) {
            array_push($lines, ...$this->event($row, $currency));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(fn (string $line): string => $this->fold($line), $lines)) . "\r\n";
    }

    /**
     * @param array<string, mixed> $row
     * @return list<string>
     */
    private function event(array $row, string $currency): array
    {
        $timezone = new DateTimeZone('UTC');
        $start = new DateTimeImmutable((string) $row['proxima_ejecucion'], $timezone);
        $end = $start->modify('+15 minutes');
        $frequency = $this->frequency((string) $row['recurrencia']);
        $currency = (string) ($row['moneda'] ?? $currency);
        $description = __('calendar.ics.amount', [
            ':amount' => format_money((int) $row['monto_centavos'], $currency),
        ]) . "\n" . __('calendar.ics.recurrence', [
            ':recurrence' => __('onboarding.recurrencia.' . (string) $row['recurrencia']),
        ]);

        if (!empty($row['deuda_id'])) {
            $description .= "\n" . __('calendar.ics.debt_reminder');
        }

        return [
            'BEGIN:VEVENT',
            'UID:recurring-' . (int) $row['id'] . '@cauce',
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . $start->format('Ymd\THis\Z'),
            'DTEND:' . $end->format('Ymd\THis\Z'),
            'SUMMARY:' . $this->escape(__('calendar.ics.summary', [':name' => (string) $row['nombre']])),
            'DESCRIPTION:' . $this->escape($description),
            'STATUS:CONFIRMED',
            'TRANSP:TRANSPARENT',
            'RRULE:FREQ=' . $frequency,
            'END:VEVENT',
        ];
    }

    private function frequency(string $recurrence): string
    {
        return match ($recurrence) {
            'diaria' => 'DAILY',
            'semanal' => 'WEEKLY',
            'anual' => 'YEARLY',
            default => 'MONTHLY',
        };
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $value
        );
    }

    /**
     * Pliega líneas largas a 75 octetos sin romper caracteres UTF-8.
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $segments = [];

        while ($line !== '') {
            $limit = $segments === [] ? 75 : 74;
            $chunk = mb_strcut($line, 0, $limit, 'UTF-8');

            if ($chunk === '') {
                $chunk = substr($line, 0, 1);
            }

            $segments[] = ($segments === [] ? '' : ' ') . $chunk;
            $line = substr($line, strlen($chunk));
        }

        return implode("\r\n", $segments);
    }
}
