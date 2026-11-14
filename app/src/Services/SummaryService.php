<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\DonationRepository;
use Cauce\Repositories\GastoRepository;
use Cauce\Repositories\IngresoRepository;
use Cauce\Repositories\InvestmentRepository;
use Cauce\Repositories\MoveRepository;
use Cauce\Repositories\NoControladoRepository;
use Cauce\Repositories\NotificationRepository;
use Cauce\Repositories\SavingsRepository;
use Cauce\Repositories\SummaryRepository;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Resumen mensual inmutable de un período cerrado.
 */
final class SummaryService
{
    /**
     * @param array<string, mixed> $user
     * @param array{inicio: string, fin: string, inicio_dt: DateTimeImmutable, fin_dt: DateTimeImmutable} $period
     * @return array<string, mixed>|null
     */
    public function ensure(array $user, array $period): ?array
    {
        $repo = new SummaryRepository();
        $existing = $repo->find((int) $user['id'], $period['inicio'], $period['fin']);

        if ($existing !== null) {
            return $existing;
        }

        $content = $this->build($user, $period);

        if (!$this->hasActivity($content)) {
            return null;
        }

        $id = $repo->create((int) $user['id'], $period['inicio'], $period['fin'], $content);

        return $repo->findForUser($id, (int) $user['id']);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    public function dueFor(array $user): ?array
    {
        $support = new FinanceSupportService();
        $current = $support->period($user);
        $previous = $support->previousPeriod($user, $current);
        $timezone = $support->ago($user);
        $config = (new NotificationRepository())->configForUser((int) $user['id']);
        $hour = (string) ($config['resumen_mensual']['hora'] ?? '10:00:00');
        [$h, $m] = array_pad(array_map('intval', explode(':', $hour)), 2, 0);
        $due = $current['inicio_dt']->setTimezone($timezone)->setTime($h, $m);
        $now = new DateTimeImmutable('now', $timezone);

        if ($now < $due) {
            return null;
        }

        return $this->ensure($user, $previous);
    }

    /**
     * @param array<string, mixed> $content
     */
    public function hasActivity(array $content): bool
    {
        return (int) ($content['income_centavos'] ?? 0) > 0
            || (int) ($content['expense_centavos'] ?? 0) > 0
            || (int) ($content['savings_centavos'] ?? 0) > 0
            || (int) ($content['investment_centavos'] ?? 0) > 0
            || (int) ($content['donation_centavos'] ?? 0) > 0
            || ($content['moves'] ?? []) !== []
            || ($content['withdrawals'] ?? []) !== []
            || ($content['contributions'] ?? []) !== [];
    }

    /**
     * @param array<string, mixed> $user
     * @param array{inicio: string, fin: string} $period
     * @return array<string, mixed>
     */
    public function build(array $user, array $period): array
    {
        $support = new FinanceSupportService();
        $userId = (int) $user['id'];
        $income = (new IngresoRepository())->sumForPeriod($userId, $period['inicio'], $period['fin']);
        $expense = (new GastoRepository())->sumByPeriod($userId, $period['inicio'], $period['fin']);
        $savings = (new SavingsRepository())->sumPeriodContributions($userId, $period['inicio'], $period['fin']);
        $savingsOut = (new SavingsRepository())->sumPeriodWithdrawals($userId, $period['inicio'], $period['fin']);
        $investment = (new InvestmentRepository())->sumContributions($userId, null, $period['inicio'], $period['fin']);
        $investmentOut = (new InvestmentRepository())->sumWithdrawals($userId, null, $period['inicio'], $period['fin']);
        $donation = (new DonationRepository())->sumPeriod($userId, $period['inicio'], $period['fin']);
        $uncontrolled = (new NoControladoRepository())->totalForPeriod($userId, $period['inicio'], $period['fin']);

        $categories = [];
        $gastos = new GastoRepository();

        foreach ($support->categories($user) as $category) {
            $name = (string) $category['nombre'];
            $assigned = $gastos->sumAssignedForPart($userId, $name, $period['inicio'], $period['fin']);
            $spent = $gastos->sumSpentForPart($userId, $name, $period['inicio'], $period['fin']);

            if ($assigned === 0 && $spent === 0) {
                continue;
            }

            $categories[] = [
                'nombre' => $name,
                'asignado_centavos' => $assigned,
                'gastado_centavos' => $spent,
                'exceso_centavos' => max(0, $spent - $assigned),
            ];
        }

        $moves = array_map(static fn (array $row): array => [
            'origen' => (string) $row['origen'],
            'destino' => (string) $row['destino'],
            'monto_centavos' => (int) ($row['monto_principal_centavos'] ?? $row['monto_centavos']),
            'motivo' => (string) $row['motivo_catalogo'],
            'texto' => $row['motivo_texto'] !== null ? (string) $row['motivo_texto'] : null,
            'fecha' => (string) $row['fecha'],
        ], (new MoveRepository())->forPeriod($userId, $period['inicio'], $period['fin']));

        $withdrawals = [];

        foreach ((new SavingsRepository())->withdrawalsForPeriod($userId, $period['inicio'], $period['fin']) as $row) {
            $withdrawals[] = [
                'tipo' => 'ahorro',
                'monto_centavos' => (int) ($row['monto_principal_centavos'] ?? $row['monto_centavos']),
                'motivo' => (string) $row['motivo_catalogo'],
                'texto' => $row['motivo_texto'] !== null ? (string) $row['motivo_texto'] : null,
                'fecha' => (string) $row['fecha'],
            ];
        }

        foreach ((new InvestmentRepository())->withdrawalsForPeriod($userId, $period['inicio'], $period['fin']) as $row) {
            $withdrawals[] = [
                'tipo' => 'inversion',
                'monto_centavos' => (int) ($row['monto_principal_centavos'] ?? $row['monto_centavos']),
                'motivo' => (string) $row['motivo_catalogo'],
                'texto' => $row['motivo_texto'] !== null ? (string) $row['motivo_texto'] : null,
                'fecha' => (string) $row['fecha'],
            ];
        }

        $contributions = [];

        foreach ((new SavingsRepository())->contributionsForPeriod($userId, $period['inicio'], $period['fin']) as $row) {
            $contributions[] = [
                'tipo' => 'ahorro',
                'monto_centavos' => (int) ($row['monto_principal_centavos'] ?? $row['monto_centavos']),
                'fecha' => (string) $row['fecha'],
            ];
        }

        foreach ((new InvestmentRepository())->contributionsForPeriod($userId, $period['inicio'], $period['fin']) as $row) {
            $contributions[] = [
                'tipo' => 'inversion',
                'monto_centavos' => (int) ($row['monto_principal_centavos'] ?? $row['monto_centavos']),
                'fecha' => (string) $row['fecha'],
            ];
        }

        return [
            'income_centavos' => $income,
            'expense_centavos' => $expense,
            'percent_spent' => $income > 0 ? round(($expense / $income) * 100, 1) : 0.0,
            'savings_centavos' => $savings - $savingsOut,
            'investment_centavos' => $investment - $investmentOut,
            'donation_centavos' => $donation,
            'no_controlado_centavos' => $uncontrolled,
            'categories' => $categories,
            'moves' => $moves,
            'withdrawals' => $withdrawals,
            'contributions' => $contributions,
            'moneda' => (string) ($user['moneda_activa'] ?? 'MXN'),
            'generated_at' => gmdate('c'),
        ];
    }
}
