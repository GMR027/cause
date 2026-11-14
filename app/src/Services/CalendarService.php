<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Repositories\IngresoRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Calendario mensual y agenda: gastos, movimientos y gastos programados.
 */
final class CalendarService
{
    private const COLOR_COUNT = 6;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function data(array $user, ?string $month = null, ?string $selected = null): array
    {
        $support = new FinanceSupportService();
        $timezone = $support->ago($user);
        $now = new DateTimeImmutable('now', $timezone);
        $today = $now->format('Y-m-d');
        $month = $this->normalizeMonth($month, $today);

        if ($selected !== null && $this->isValidDate($selected)) {
            $month = substr($selected, 0, 7);
        }

        $monthStart = new DateTimeImmutable($month . '-01 00:00:00', $timezone);
        $monthEnd = $monthStart->modify('last day of this month')->setTime(23, 59, 59);
        $firstWeekday = $this->firstWeekday();
        $offset = ((int) $monthStart->format('w') - $firstWeekday + 7) % 7;
        $gridStart = $monthStart->modify('-' . $offset . ' days');
        $gridEnd = $gridStart->modify('+41 days')->setTime(23, 59, 59);

        $expenses = $this->expenses($user, $gridStart, $gridEnd, $timezone);
        $moves = $this->moves($user, $gridStart, $gridEnd, $timezone);
        $scheduled = $this->scheduled($user, $monthStart, $monthEnd, $timezone);

        $eventsByDate = [];

        foreach ($expenses as $event) {
            $eventsByDate[$event['date']][] = $event;
        }

        foreach ($moves as $event) {
            $eventsByDate[$event['date']][] = $event;
        }

        foreach ($scheduled as $event) {
            $eventsByDate[$event['date']][] = $event;
        }

        foreach ($eventsByDate as $date => $events) {
            usort($events, static fn (array $a, array $b): int => strcmp((string) $a['time'], (string) $b['time']));
            $eventsByDate[$date] = $events;
        }

        $income = $this->nextIncome($user, $now, $monthStart, $monthEnd, $timezone);
        $bands = $this->bands($user, $gridStart, $gridEnd, $timezone);

        $days = [];

        for ($i = 0; $i < 42; $i++) {
            $date = $gridStart->modify('+' . $i . ' days');
            $key = $date->format('Y-m-d');
            $events = $eventsByDate[$key] ?? [];
            $pending = false;
            $hasScheduled = false;
            $dots = [];

            foreach ($events as $event) {
                if ($event['type'] === 'programado') {
                    $hasScheduled = true;
                    continue;
                }

                if ($event['pending']) {
                    $pending = true;
                }

                if ($event['type'] === 'gasto') {
                    $dots[$event['color']] = true;
                }
            }

            $days[] = [
                'date' => $key,
                'day' => (int) $date->format('j'),
                'in_month' => $date->format('Y-m') === $month,
                'is_today' => $key === $today,
                'is_selected' => $key === $this->selectedDate($selected, $today, $month, $monthStart),
                'weekday' => (int) $date->format('w'),
                'band' => $bands['days'][$key] ?? 0,
                'events' => $events,
                'event_count' => count($events),
                'pending' => $pending,
                'scheduled' => $hasScheduled,
                'dots' => array_keys($dots),
                'income' => $income !== null && $income['date'] === $key,
            ];
        }

        $selectedDate = $this->selectedDate($selected, $today, $month, $monthStart);
        $agenda = [];
        $monthPrefix = $month . '-';

        foreach ($eventsByDate as $date => $events) {
            if (!str_starts_with($date, $monthPrefix)) {
                continue;
            }

            $agenda[] = [
                'date' => $date,
                'label' => $this->dateLabel($date, $timezone),
                'events' => $events,
                'pending' => (bool) array_filter($events, static fn (array $e): bool => $e['pending']),
                'is_today' => $date === $today,
            ];
        }

        usort($agenda, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        if ($income !== null && !empty($income['in_month'])) {
            $merged = false;

            foreach ($agenda as &$item) {
                if ($item['date'] === $income['date']) {
                    array_unshift($item['events'], $income);
                    $item['pending'] = $item['pending'] || $income['pending'];
                    $merged = true;
                    break;
                }
            }

            unset($item);

            if (!$merged) {
                $agenda[] = [
                    'date' => $income['date'],
                    'label' => $this->dateLabel($income['date'], $timezone),
                    'events' => [$income],
                    'pending' => $income['pending'],
                    'is_today' => $income['date'] === $today,
                ];
                usort($agenda, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));
            }
        }

        if ($selected !== null && $this->isValidDate($selected)) {
            $selectedDate = $selected;
        }

        $selectedEvents = $eventsByDate[$selectedDate] ?? [];

        if ($income !== null && $income['date'] === $selectedDate) {
            array_unshift($selectedEvents, $income);
        }

        $monthTotal = 0;
        $pendingTotal = 0;
        $pendingCount = 0;

        foreach ($expenses as $event) {
            if (!str_starts_with($event['date'], $monthPrefix)) {
                continue;
            }

            $monthTotal += (int) ($event['amount_principal'] ?? $event['amount']);

            if ($event['pending']) {
                $pendingTotal += (int) ($event['amount_principal'] ?? $event['amount']);
                $pendingCount++;
            }
        }

        return [
            'month' => $month,
            'month_label' => $this->monthLabel($month),
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
            'prev_month' => $monthStart->modify('-1 month')->format('Y-m'),
            'next_month' => $monthStart->modify('+1 month')->format('Y-m'),
            'first_weekday' => $firstWeekday,
            'weekdays' => $this->weekdays($firstWeekday),
            'days' => $days,
            'bands' => $bands['bands'],
            'agenda' => $agenda,
            'selected_date' => $selectedDate,
            'selected_label' => $this->dateLabel($selectedDate, $timezone),
            'selected_events' => $selectedEvents,
            'income' => $income,
            'today' => $today,
            'month_total' => $monthTotal,
            'pending_total' => $pendingTotal,
            'pending_count' => $pendingCount,
            'scheduled_count' => count($scheduled),
            'currency' => (string) ($user['moneda_activa'] ?? 'MXN'),
            'timezone' => $timezone->getName(),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    public function day(array $user, string $date): ?array
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        $data = $this->data($user, substr($date, 0, 7), $date);
        $events = [];

        foreach ($data['days'] as $day) {
            if ($day['date'] === $date) {
                $events = $day['events'];
                break;
            }
        }

        if ($data['income'] !== null && $data['income']['date'] === $date) {
            array_unshift($events, $data['income']);
        }

        return [
            'date' => $date,
            'label' => $data['selected_label'],
            'events' => $events,
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    private function expenses(
        array $user,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        DateTimeZone $timezone
    ): array {
        $treatment = (new MethodService())->activeData($user);
        $colors = [];
        $index = 0;

        if ($treatment !== null) {
            foreach ($treatment['method']['categorias'] as $category) {
                $colors[(string) $category['nombre']] = ($index % self::COLOR_COUNT) + 1;
                $index++;
            }
        }

        $stmt = $this->db->prepare(
            'SELECT id, fecha, monto_centavos, monto_principal_centavos, moneda, parte_metodo, subcategoria_nombre, nota, estado, confirmado
             FROM gastos
             WHERE user_id = ? AND fecha BETWEEN ? AND ?
             ORDER BY fecha ASC, id ASC'
        );
        $stmt->execute([
            (int) $user['id'],
            $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);
        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $local = (new DateTimeImmutable((string) $row['fecha'], new DateTimeZone('UTC')))->setTimezone($timezone);
            $part = (string) $row['parte_metodo'];
            $rows[] = [
                'type' => 'gasto',
                'date' => $local->format('Y-m-d'),
                'time' => $local->format('H:i'),
                'title' => (string) ($row['subcategoria_nombre'] ?? $part),
                'subtitle' => (string) ($row['nota'] ?? $part),
                'amount' => (int) $row['monto_centavos'],
                'amount_principal' => (int) ($row['monto_principal_centavos'] ?? $row['monto_centavos']),
                'currency' => (string) $row['moneda'],
                'color' => $colors[$part] ?? 0,
                'pending' => (string) $row['estado'] === 'pendiente_asignacion' || (int) $row['confirmado'] === 0,
                'scheduled' => false,
                'url' => '/gastos/ver?id=' . (int) $row['id'],
                'icon' => 'bi-receipt',
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    private function moves(
        array $user,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        DateTimeZone $timezone
    ): array {
        $stmt = $this->db->prepare(
            'SELECT id, fecha, monto_centavos, monto_principal_centavos, moneda, origen, destino, motivo_catalogo
             FROM movimientos
             WHERE user_id = ? AND fecha BETWEEN ? AND ?
             ORDER BY fecha ASC, id ASC'
        );
        $stmt->execute([
            (int) $user['id'],
            $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);
        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $local = (new DateTimeImmutable((string) $row['fecha'], new DateTimeZone('UTC')))->setTimezone($timezone);
            $rows[] = [
                'type' => 'movimiento',
                'date' => $local->format('Y-m-d'),
                'time' => $local->format('H:i'),
                'title' => (string) $row['origen'] . ' → ' . (string) $row['destino'],
                'subtitle' => __('moves.reasons.' . (string) $row['motivo_catalogo']),
                'amount' => (int) $row['monto_centavos'],
                'amount_principal' => (int) ($row['monto_principal_centavos'] ?? $row['monto_centavos']),
                'currency' => (string) $row['moneda'],
                'color' => 0,
                'pending' => false,
                'scheduled' => false,
                'url' => '/categorias/ver?parte=' . rawurlencode((string) $row['origen']),
                'icon' => 'bi-arrow-left-right',
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    private function scheduled(
        array $user,
        DateTimeImmutable $monthStart,
        DateTimeImmutable $monthEnd,
        DateTimeZone $timezone
    ): array {
        $stmt = $this->db->prepare(
            'SELECT id, nombre, monto_centavos, monto_principal_centavos, moneda, parte_metodo, recurrencia, proxima_ejecucion
             FROM gastos_recurrentes
             WHERE user_id = ? AND activo = 1
             ORDER BY proxima_ejecucion ASC'
        );
        $stmt->execute([(int) $user['id']]);
        $recurrence = new RecurrenceService();
        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $cursor = (new DateTimeImmutable((string) $row['proxima_ejecucion'], new DateTimeZone('UTC')))
                ->setTimezone($timezone);
            $guard = 0;

            while ($cursor < $monthStart && $guard < 400) {
                $cursor = $recurrence->next($cursor, (string) $row['recurrencia'], $timezone);
                $guard++;
            }

            $count = 0;

            while ($cursor <= $monthEnd && $count < 62) {
                $rows[] = [
                    'type' => 'programado',
                    'date' => $cursor->format('Y-m-d'),
                    'time' => $cursor->format('H:i'),
                    'title' => (string) $row['nombre'],
                    'subtitle' => (string) $row['parte_metodo'],
                    'amount' => (int) $row['monto_centavos'],
                'amount_principal' => (int) ($row['monto_principal_centavos'] ?? $row['monto_centavos']),
                    'currency' => (string) $row['moneda'],
                    'color' => 0,
                    'pending' => false,
                    'scheduled' => true,
                    'url' => '/ajustes/recurrentes',
                    'icon' => 'bi-arrow-repeat',
                ];
                $cursor = $recurrence->next($cursor, (string) $row['recurrencia'], $timezone);
                $count++;
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    private function nextIncome(
        array $user,
        DateTimeImmutable $now,
        DateTimeImmutable $monthStart,
        DateTimeImmutable $monthEnd,
        DateTimeZone $timezone
    ): ?array {
        $support = new FinanceSupportService();
        $current = $support->period($user);
        $incomes = new IngresoRepository();
        $hasIncome = $incomes->existsForPeriod((int) $user['id'], $current['inicio'], $current['fin']);
        $currentStart = $current['inicio_dt']->setTimezone($timezone);
        $todayStart = $now->setTime(0, 0);

        if (!$hasIncome && $currentStart >= $todayStart) {
            $date = $currentStart;
            $pending = true;
        } else {
            $date = $current['fin_dt']->setTimezone($timezone)->modify('+1 second')->setTime(9, 0);
            $pending = false;
        }

        $amount = $incomes->lastFixedAmount((int) $user['id']);

        return [
            'type' => 'ingreso',
            'date' => $date->format('Y-m-d'),
            'time' => $date->format('H:i'),
            'title' => __('calendar.next_income'),
            'subtitle' => $pending ? __('calendar.income_pending') : __('calendar.income_expected'),
            'amount' => $amount ?? 0,
            'currency' => (string) ($user['moneda_activa'] ?? 'MXN'),
            'color' => 0,
            'pending' => $pending,
            'scheduled' => !$pending,
            'url' => '/ingresos/nuevo',
            'icon' => 'bi-arrow-down-circle',
            'in_month' => $date >= $monthStart && $date <= $monthEnd,
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array{bands: list<array<string, mixed>>, days: array<string, int>}
     */
    private function bands(
        array $user,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        DateTimeZone $timezone
    ): array {
        $periods = new PeriodoService();
        $bands = [];
        $days = [];
        $currentKey = null;
        $index = 0;

        for ($i = 0; $i < 42; $i++) {
            $date = $start->modify('+' . $i . ' days');
            $reference = $date->setTime(12, 0);
            $period = $periods->calcularPeriodo($user, $reference);
            $key = $period['inicio']->format('Y-m-d H:i:s') . '|' . $period['fin']->format('Y-m-d H:i:s');

            if ($key !== $currentKey) {
                $index++;
                $currentKey = $key;
                $bands[] = [
                    'index' => $index,
                    'inicio' => $period['inicio']->setTimezone($timezone)->format('Y-m-d'),
                    'fin' => $period['fin']->setTimezone($timezone)->format('Y-m-d'),
                    'label' => format_date($period['inicio']->setTimezone($timezone))
                        . ' – ' . format_date($period['fin']->setTimezone($timezone)),
                    'current' => false,
                ];
            }

            $days[$date->format('Y-m-d')] = $index;
        }

        $today = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
        $currentIndex = $days[$today] ?? null;

        if ($currentIndex !== null) {
            foreach ($bands as &$band) {
                $band['current'] = $band['index'] === $currentIndex;
            }

            unset($band);
        }

        return ['bands' => $bands, 'days' => $days];
    }

    private function normalizeMonth(?string $month, string $today): string
    {
        if (is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1) {
            return $month;
        }

        return substr($today, 0, 7);
    }

    private function selectedDate(?string $selected, string $today, string $month, DateTimeImmutable $monthStart): string
    {
        if ($selected !== null && $this->isValidDate($selected)) {
            return $selected;
        }

        return str_starts_with($today, $month . '-') ? $today : $monthStart->format('Y-m-d');
    }

    private function isValidDate(string $date): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches) !== 1) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }

    private function firstWeekday(): int
    {
        $value = \Cauce\Helpers\I18n::get('calendar.first_weekday');

        return is_int($value) ? $value : 1;
    }

    /**
     * @return list<string>
     */
    private function weekdays(int $firstWeekday): array
    {
        $names = lang_array('calendar.weekdays_short');

        if (count($names) !== 7) {
            $names = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
        }

        $names = array_values($names);
        $rotated = array_merge(array_slice($names, $firstWeekday), array_slice($names, 0, $firstWeekday));

        return array_map(static fn (mixed $name): string => (string) $name, $rotated);
    }

    private function monthLabel(string $month): string
    {
        $date = new DateTimeImmutable($month . '-01 12:00:00');
        $months = lang_array('calendar.months');
        $name = is_array($months) ? (string) ($months[(int) $date->format('n')] ?? '') : '';

        return trim($name . ' ' . $date->format('Y'));
    }

    private function dateLabel(string $date, DateTimeZone $timezone): string
    {
        $value = new DateTimeImmutable($date . ' 12:00:00', $timezone);
        $months = lang_array('calendar.months_short');
        $weekdays = lang_array('calendar.weekdays_long');
        $month = is_array($months) ? (string) ($months[(int) $value->format('n')] ?? '') : '';
        $weekday = is_array($weekdays) ? (string) ($weekdays[(int) $value->format('w')] ?? '') : '';

        return trim($weekday . ' ' . $value->format('j') . ' ' . $month);
    }
}
