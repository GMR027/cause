<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Helpers\Logger;
use Cauce\Repositories\GastoRepository;
use Cauce\Repositories\IngresoRepository;
use Cauce\Repositories\RecurringExpenseRepository;
use Cauce\Repositories\SubcategoriaRepository;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

/**
 * Ejecución perezosa de gastos recurrentes.
 *
 * Se llama desde un middleware: no requiere cron externo. Cada ocurrencia se
 * registra con su fecha programada y su período histórico.
 */
final class RecurringExpenseService
{
    public function runDueForUser(array $user, DateTimeInterface|string|null $now = null): int
    {
        $utc = new DateTimeZone('UTC');
        $nowUtc = $now === null
            ? new DateTimeImmutable('now', $utc)
            : DateTimeImmutable::createFromInterface(
                $now instanceof DateTimeInterface ? $now : new DateTimeImmutable((string) $now, $utc)
            )->setTimezone($utc);

        $repo = new RecurringExpenseRepository();
        $due = $repo->dueForUser((int) $user['id'], $nowUtc->format('Y-m-d H:i:s'));

        if ($due === []) {
            return 0;
        }

        $timezone = (new PeriodoService())->userTimezone($user);
        $periods = new PeriodoService();
        $recurrence = new RecurrenceService();
        $gastos = new GastoService();
        $created = 0;

        foreach ($due as $recurring) {
            $db = Database::connection();
            $createdNow = [];
            $debtReminders = [];

            try {
                $db->beginTransaction();
                $locked = $repo->lockForUpdate((int) $recurring['id']);

                if ($locked === null || (int) $locked['activo'] !== 1) {
                    $db->commit();
                    continue;
                }

                $nextUtc = new DateTimeImmutable((string) $locked['proxima_ejecucion'], $utc);

                // Si la fecha quedó muy atrás, no se generan años de gastos de golpe.
                if ($nextUtc < $nowUtc->modify('-60 days')) {
                    $nextUtc = $nowUtc;
                }

                $dates = $recurrence->dueDates(
                    $nextUtc,
                    $nowUtc,
                    (string) $locked['recurrencia'],
                    $timezone,
                    24
                );

                $last = null;

                if (!empty($locked['deuda_id'])) {
                    foreach ($dates as $localDate) {
                        $debtReminders[] = [
                            'deuda_id' => (int) $locked['deuda_id'],
                            'name' => (string) $locked['nombre'],
                            'amount' => (int) $locked['monto_centavos'],
                            'currency' => (string) $locked['moneda'],
                            'date' => $localDate->setTimezone($utc)->format('Y-m-d'),
                        ];
                        $last = $localDate;
                    }

                    if ($last !== null) {
                        $next = $recurrence->next($last, (string) $locked['recurrencia'], $timezone);
                        $repo->advance((int) $locked['id'], $next->setTimezone($utc)->format('Y-m-d H:i:s'));
                    }

                    $db->commit();

                    foreach ($debtReminders as $reminder) {
                        try {
                            (new NotificationService())->notifyDebtReminder(
                                $user,
                                $reminder['deuda_id'],
                                $reminder['name'],
                                $reminder['amount'],
                                $reminder['date'],
                                $reminder['currency']
                            );
                        } catch (Throwable $exception) {
                            Logger::warning('No se pudo notificar el recordatorio de deuda', [
                                'error' => $exception->getMessage(),
                            ]);
                        }
                    }

                    $created += count($debtReminders);
                    continue;
                }

                foreach ($dates as $localDate) {
                    $dateUtc = $localDate->setTimezone($utc);
                    $period = $periods->calcularPeriodo($user, $dateUtc);
                    $inicio = $period['inicio']->format('Y-m-d H:i:s');
                    $fin = $period['fin']->format('Y-m-d H:i:s');
                    $hasIncome = (new IngresoRepository($db))->existsForPeriod((int) $user['id'], $inicio, $fin);
                    $subcategoryId = $locked['subcategoria_id'] !== null ? (int) $locked['subcategoria_id'] : null;
                    $subcategoryName = null;

                    if ($subcategoryId !== null) {
                        $subcategory = (new SubcategoriaRepository($db))->findForUser($subcategoryId, (int) $user['id']);
                        $subcategoryName = $subcategory['nombre'] ?? null;
                    }

                    $result = $gastos->crearRecurrente($user, [
                        'monto_centavos' => (int) $locked['monto_centavos'],
                        'moneda' => (string) $locked['moneda'],
                        'parte_metodo' => (string) $locked['parte_metodo'],
                        'subcategoria_id' => $subcategoryId,
                        'subcategoria_nombre' => $subcategoryName,
                        'fecha' => $dateUtc->format('Y-m-d H:i:s'),
                        'nota' => (string) $locked['nombre'],
                        'medio_pago' => null,
                        'monto_total_compartido_centavos' => null,
                        'fuente_exceso' => 'no_aplica',
                        'tags' => [],
                        'gasto_recurrente_id' => (int) $locked['id'],
                    ], ['inicio' => $inicio, 'fin' => $fin], $hasIncome);

                    $createdNow[] = [
                        'gasto_id' => $result['gasto_id'] ?? 0,
                        'name' => (string) $locked['nombre'],
                        'amount' => (int) $locked['monto_centavos'],
                        'currency' => (string) $locked['moneda'],
                        'part' => (string) $locked['parte_metodo'],
                    ];

                    $created++;
                    $last = $localDate;
                }

                if ($last !== null) {
                    $next = $recurrence->next($last, (string) $locked['recurrencia'], $timezone);
                    $repo->advance((int) $locked['id'], $next->setTimezone($utc)->format('Y-m-d H:i:s'));
                }

                $db->commit();

                foreach ($createdNow as $item) {
                    try {
                        (new NotificationService())->notifyRecurring(
                            $user,
                            (int) $item['gasto_id'],
                            (string) $item['name'],
                            (int) $item['amount'],
                            (string) $item['part'],
                            gmdate('Y-m-d H:i:s'),
                            (string) $item['currency']
                        );
                    } catch (Throwable $exception) {
                        Logger::warning('No se pudo notificar el recurrente', [
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }

                Logger::error('No se pudo ejecutar un gasto recurrente', [
                    'user_id' => $user['id'],
                    'recurrente_id' => $recurring['id'] ?? null,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $created;
    }
}
