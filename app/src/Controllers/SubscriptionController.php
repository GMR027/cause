<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Repositories\RecurringExpenseRepository;
use Cauce\Services\AuthService;
use Cauce\Services\CurrencyService;
use Cauce\Services\GastoService;
use Cauce\Services\PeriodoService;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Registro y seguimiento de suscripciones (Reportes → Suscripciones).
 *
 * Cada suscripción es un gasto recurrente: se cobra sola en su fecha y
 * descuenta de su categoría, con datos extra de seguimiento.
 */
final class SubscriptionController
{
    private const BACK = '/reportes/suscripciones';

    public function store(): void
    {
        $this->save(null);
    }

    public function update(): void
    {
        $this->save((int) ($_POST['id'] ?? 0));
    }

    public function cancel(): void
    {
        $user = $this->user();
        $id = (int) ($_POST['id'] ?? 0);
        $repo = new RecurringExpenseRepository();
        $row = $repo->findForUser($id, (int) $user['id']);

        if ($row === null || !empty($row['deuda_id'])) {
            Flash::error('reports.subscriptions.errors.not_found');
            redirect(self::BACK);
        }

        $repo->cancelSubscription($id, (int) $user['id']);
        Flash::success('reports.subscriptions.flash.cancelled', [':name' => (string) $row['nombre']]);
        redirect(self::BACK);
    }

    public function reactivate(): void
    {
        $user = $this->user();
        $id = (int) ($_POST['id'] ?? 0);
        $repo = new RecurringExpenseRepository();
        $row = $repo->findForUser($id, (int) $user['id']);

        if ($row === null || (int) $row['activo'] === 1) {
            Flash::error('reports.subscriptions.errors.not_found');
            redirect(self::BACK);
        }

        $timezone = (new PeriodoService())->userTimezone($user);
        $next = $this->parseDate((string) input('proxima_ejecucion', ''), $timezone);

        if ($next === null || $next < (new DateTimeImmutable('today', $timezone))) {
            Flash::error('reports.subscriptions.errors.date');
            redirect(self::BACK);
        }

        $repo->reactivateSubscription($id, (int) $user['id'], $next->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
        Flash::success('reports.subscriptions.flash.reactivated', [':name' => (string) $row['nombre']]);
        redirect(self::BACK);
    }

    private function save(?int $id): void
    {
        $user = $this->user();
        $repo = new RecurringExpenseRepository();
        $categories = array_column((new GastoService())->categoriasGasto($user), 'nombre');
        $timezone = (new PeriodoService())->userTimezone($user);

        $name = trim((string) input('nombre', ''));
        $amount = parse_money_cents((string) input('monto', ''));
        $part = (string) input('parte_metodo', '');
        $recurrence = (string) input('recurrencia', 'mensual');
        $next = $this->parseDate((string) input('proxima_ejecucion', ''), $timezone);
        $provider = trim((string) input('proveedor', ''));
        $trialRaw = trim((string) input('prueba_hasta', ''));
        $trial = $trialRaw !== '' ? $this->parseDate($trialRaw, $timezone) : null;
        $reminderRaw = trim((string) input('recordatorio_dias', ''));
        $notes = trim((string) input('notas', ''));
        $variable = !empty($_POST['monto_variable']);

        $error = match (true) {
            $name === '' => 'reports.subscriptions.errors.name',
            $amount === null || $amount <= 0 => 'reports.subscriptions.errors.amount',
            !in_array($part, $categories, true) => 'reports.subscriptions.errors.category',
            !in_array($recurrence, ['diaria', 'semanal', 'mensual', 'anual'], true) => 'reports.subscriptions.errors.frequency',
            $next === null || $next < new DateTimeImmutable('today', $timezone) => 'reports.subscriptions.errors.date',
            $trialRaw !== '' && $trial === null => 'reports.subscriptions.errors.trial',
            $reminderRaw !== '' && (!ctype_digit($reminderRaw) || (int) $reminderRaw > 30) => 'reports.subscriptions.errors.reminder',
            default => null,
        };

        if ($error === null && $id !== null) {
            $existing = $repo->findForUser($id, (int) $user['id']);

            if ($existing === null || !empty($existing['deuda_id'])) {
                $error = 'reports.subscriptions.errors.not_found';
            }
        }

        if ($error !== null) {
            Flash::error($error);
            redirect(self::BACK);
        }

        $nextUtc = $next->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        try {
            if ($id === null) {
                $currency = (new CurrencyService())->validateCode(
                    trim((string) input('moneda', '')) !== '' ? (string) input('moneda', '') : (string) ($user['moneda_activa'] ?? 'MXN')
                );
                $id = $repo->create((int) $user['id'], mb_substr($name, 0, 120), $amount, $currency, $variable, $part, null, $recurrence, $nextUtc);
            } else {
                $existing = $repo->findForUser($id, (int) $user['id']);
                $repo->update(
                    $id,
                    (int) $user['id'],
                    $name,
                    $amount,
                    $variable,
                    $part,
                    $existing['subcategoria_id'] !== null ? (int) $existing['subcategoria_id'] : null,
                    $recurrence,
                    $nextUtc
                );
            }

            $repo->setSubscription(
                $id,
                (int) $user['id'],
                $provider !== '' ? mb_substr($provider, 0, 80) : null,
                $trial?->format('Y-m-d'),
                $reminderRaw !== '' ? (int) $reminderRaw : null,
                $notes !== '' ? mb_substr($notes, 0, 255) : null
            );
        } catch (Throwable $exception) {
            Flash::error($exception->getMessage() === 'sin_tasa' ? 'currency.errors.no_rate' : 'reports.subscriptions.errors.generic');
            redirect(self::BACK);
        }

        Flash::success(isset($existing) ? 'reports.subscriptions.flash.updated' : 'reports.subscriptions.flash.created');
        redirect(self::BACK);
    }

    private function parseDate(string $date, DateTimeZone $timezone): ?DateTimeImmutable
    {
        $date = trim($date);

        if ($date === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            return null;
        }

        return $parsed->setTime(9, 0, 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function user(): array
    {
        $user = AuthService::user();

        if ($user === null) {
            redirect('/login');
        }

        return $user;
    }
}
