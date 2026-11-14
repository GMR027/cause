<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\View;
use Cauce\Repositories\RecurringExpenseRepository;
use Cauce\Repositories\SubcategoriaRepository;
use Cauce\Services\AuthService;
use Cauce\Services\GastoService;
use Cauce\Services\PeriodoService;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Configuración de gastos recurrentes.
 */
final class RecurringController
{
    public function index(): void
    {
        $user = $this->user();
        $service = new GastoService();
        $repo = new RecurringExpenseRepository();
        $editing = null;

        if (isset($_GET['editar'])) {
            $editing = $repo->findForUser((int) $_GET['editar'], (int) $user['id']);
        }

        echo View::render('ajustes/recurrentes', [
            'title' => __('expenses.recurring.title'),
            'recurrings' => $repo->allForUser((int) $user['id'], false),
            'editing' => $editing,
            'categories' => $service->categoriasGasto($user),
            'subcategories' => (new SubcategoriaRepository())->allForUser((int) $user['id']),
        ], 'layouts/app');
    }

    public function store(): void
    {
        $this->save(null);
    }

    public function update(): void
    {
        $this->save((int) ($_POST['id'] ?? 0));
    }

    public function deactivate(): void
    {
        $user = $this->user();
        $id = (int) ($_POST['id'] ?? 0);
        $repo = new RecurringExpenseRepository();

        if ($repo->findForUser($id, (int) $user['id']) === null) {
            Flash::error('expenses.errors.not_found');
            redirect('/ajustes/recurrentes');
        }

        $repo->deactivate($id, (int) $user['id']);
        Flash::success('expenses.flash.recurring_deactivated');
        redirect('/ajustes/recurrentes');
    }

    private function save(?int $id): void
    {
        $user = $this->user();
        $repo = new RecurringExpenseRepository();
        $service = new GastoService();
        $categories = array_column($service->categoriasGasto($user), 'nombre');

        $name = trim((string) input('nombre', ''));
        $amount = parse_money_cents((string) input('monto', ''));
        $part = (string) input('parte_metodo', '');
        $recurrence = (string) input('recurrencia', 'mensual');
        $subcategoryId = input('subcategoria_id', '');
        $subcategoryId = $subcategoryId !== '' ? (int) $subcategoryId : null;
        $date = trim((string) input('proxima_ejecucion', ''));
        $variable = !empty($_POST['monto_variable']);

        if ($name === '') {
            Flash::error('expenses.errors.recurring_name');
            redirect('/ajustes/recurrentes');
        }

        if ($amount === null || $amount <= 0) {
            Flash::error('expenses.errors.amount');
            redirect('/ajustes/recurrentes');
        }

        if (!in_array($part, $categories, true)) {
            Flash::error('expenses.errors.part');
            redirect('/ajustes/recurrentes');
        }

        if (!in_array($recurrence, ['diaria', 'semanal', 'mensual', 'anual'], true)) {
            Flash::error('expenses.errors.recurrence');
            redirect('/ajustes/recurrentes');
        }

        if ($subcategoryId !== null && (new SubcategoriaRepository())->findForUser($subcategoryId, (int) $user['id']) === null) {
            $subcategoryId = null;
        }

        if ($id !== null && $repo->findForUser($id, (int) $user['id']) === null) {
            Flash::error('expenses.errors.not_found');
            redirect('/ajustes/recurrentes');
        }

        $timezone = (new PeriodoService())->userTimezone($user);
        $next = $this->parseDate($date, $timezone);

        if ($next === null) {
            Flash::error('expenses.errors.date');
            redirect('/ajustes/recurrentes');
        }

        $today = new \DateTimeImmutable('now', $timezone);

        if ($next < $today->setTime(0, 0)) {
            Flash::error('expenses.errors.recurring_date_past');
            redirect('/ajustes/recurrentes');
        }

        $nextUtc = $next->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        try {
        if ($id === null) {
            $repo->create(
                (int) $user['id'],
                mb_substr($name, 0, 120),
                $amount,
                (string) input('moneda', $user['moneda_activa'] ?? 'MXN'),
                $variable,
                $part,
                $subcategoryId,
                $recurrence,
                $nextUtc
            );
            Flash::success('expenses.flash.recurring_created');
        } else {
            $repo->update(
                $id,
                (int) $user['id'],
                mb_substr($name, 0, 120),
                $amount,
                $variable,
                $part,
                $subcategoryId,
                $recurrence,
                $nextUtc
            );
            Flash::success('expenses.flash.recurring_updated');
        }
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage() === 'sin_tasa' ? 'currency.errors.no_rate' : 'expenses.errors.generic');
            redirect('/ajustes/recurrentes');
        }

        redirect('/ajustes/recurrentes');
    }

    private function parseDate(string $date, DateTimeZone $timezone): ?DateTimeImmutable
    {
        if ($date === '') {
            $date = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
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
