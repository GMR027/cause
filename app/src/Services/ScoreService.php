<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Repositories\DebtRepository;
use Cauce\Repositories\IngresoRepository;
use Cauce\Repositories\SavingsRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Score privado de salud financiera y sus snapshots mensuales.
 */
final class ScoreService
{
    public const WEIGHTS = [
        'metodo' => 30,
        'ahorro' => 25,
        'deudas' => 20,
        'emergencia' => 15,
        'patrimonio' => 10,
    ];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function panel(array $user, bool $withHistory = false): array
    {
        $calculation = $this->ensureCurrentSnapshot($user);
        $previous = null;

        if (!empty($calculation['snapshot_date'])) {
            $previous = $this->previousScore((int) $user['id'], (string) $calculation['snapshot_date']);
        }

        $trend = $calculation['has_data']
            ? self::trend((int) $calculation['score'], $previous)
            : null;

        return array_merge($calculation, [
            'history' => $withHistory ? $this->history($user) : [],
            'previous_score' => $previous,
            'trend' => $trend,
        ]);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function calculate(array $user): array
    {
        $support = new FinanceSupportService();
        $period = $support->period($user);
        $income = (new IngresoRepository())->sumForPeriod(
            (int) $user['id'],
            $period['inicio'],
            $period['fin']
        );
        $periodDays = max(
            1,
            (int) round(($period['fin_dt']->getTimestamp() - $period['inicio_dt']->getTimestamp()) / 86400)
        );
        $currency = (string) ($user['moneda_activa'] ?? 'MXN');
        $base = [
            'has_data' => false,
            'score' => null,
            'components' => [],
            'income_centavos' => $income,
            'monthly_income_centavos' => 0,
            'net_worth_centavos' => 0,
            'currency' => $currency,
            'period' => $period,
            'period_days' => $periodDays,
            'reason' => 'sin_ingreso',
        ];

        if ($income <= 0) {
            return $base;
        }

        $compliance = (new MethodComplianceService())->recentAverage($user, 3);

        if ($compliance['percent'] === null) {
            return array_merge($base, ['reason' => 'sin_metodo']);
        }

        $components = [];
        $components[] = $this->component(
            'metodo',
            (float) $compliance['percent'],
            ['periodos' => count($compliance['periods']), 'porcentaje' => $compliance['percent']]
        );

        $savingsRepo = new SavingsRepository();
        $savingsNet = $savingsRepo->sumPeriodContributions((int) $user['id'], $period['inicio'], $period['fin'])
            - $savingsRepo->sumPeriodWithdrawals((int) $user['id'], $period['inicio'], $period['fin']);
        $savingsRate = $savingsNet / $income;
        $savingsScore = self::savingsScore($savingsNet, $income);
        $components[] = $this->component('ahorro', $savingsScore, [
            'ahorro_neto_centavos' => $savingsNet,
            'tasa' => round($savingsRate * 100, 2),
        ]);

        $monthlyIncome = (int) round($income * 30.44 / $periodDays);
        $debt = (new DebtRepository())->sumActiveBalance((int) $user['id']);
        $debtRatio = $monthlyIncome > 0 ? $debt / $monthlyIncome : 0.0;
        $debtScore = self::debtScore($debt, $monthlyIncome);
        $components[] = $this->component('deudas', $debtScore, [
            'saldo_centavos' => $debt,
            'ingreso_mensual_centavos' => $monthlyIncome,
            'ratio' => round($debtRatio * 100, 2),
        ]);

        $emergencySaved = $this->emergencySaved((int) $user['id']);
        $average = (new EmergencyFundService())->averageExpenses($user)['average'];
        $months = $average > 0 ? $emergencySaved / $average : 0.0;
        $emergencyScore = self::emergencyScore($emergencySaved, $average);
        $components[] = $this->component('emergencia', $emergencyScore, [
            'fondo_centavos' => $emergencySaved,
            'gasto_mensual_centavos' => $average,
            'meses' => round($months, 2),
        ]);

        $netSummary = (new NetWorthService())->summary($user);
        $netWorth = (int) $netSummary['net_contributed_centavos'];
        $previousNet = $this->previousNetWorth((int) $user['id'], $period);
        $netScore = self::netWorthScore($netWorth, $previousNet);
        $components[] = $this->component('patrimonio', $netScore, [
            'neto_centavos' => $netWorth,
            'neto_mercado_centavos' => (int) $netSummary['net_centavos'],
            'neto_anterior_centavos' => $previousNet,
        ]);

        $score = (int) round(array_sum(array_column($components, 'points')));

        return [
            'has_data' => true,
            'score' => max(0, min(100, $score)),
            'components' => $components,
            'income_centavos' => $income,
            'monthly_income_centavos' => $monthlyIncome,
            'net_worth_centavos' => $netWorth,
            'currency' => $currency,
            'period' => $period,
            'period_days' => $periodDays,
            'reason' => null,
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function ensureCurrentSnapshot(array $user): array
    {
        $calculation = $this->calculate($user);

        if (!$calculation['has_data']) {
            return array_merge($calculation, ['snapshot_date' => null]);
        }

        $timezone = (new PeriodoService())->userTimezone($user);
        $date = (new DateTimeImmutable('now', $timezone))->format('Y-m-01');
        $this->writeSnapshot($user, $date, $calculation);

        return array_merge($calculation, ['snapshot_date' => $date]);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function snapshotForPeriod(array $user, string $periodStartUtc): void
    {
        $calculation = $this->calculate($user);

        if (!$calculation['has_data']) {
            return;
        }

        $timezone = (new PeriodoService())->userTimezone($user);
        $date = (new DateTimeImmutable($periodStartUtc, new DateTimeZone('UTC')))
            ->setTimezone($timezone)
            ->format('Y-m-01');
        $this->writeSnapshot($user, $date, $calculation);
    }

    /**
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    public function history(array $user, int $months = 12): array
    {
        $timezone = (new PeriodoService())->userTimezone($user);
        $current = new DateTimeImmutable('first day of this month', $timezone);
        $start = $current->modify('-' . max(0, $months - 1) . ' months');
        $stmt = $this->db->prepare(
            'SELECT * FROM score_snapshots
             WHERE user_id = ? AND fecha >= ?
             ORDER BY fecha ASC, id ASC'
        );
        $stmt->execute([(int) $user['id'], $start->format('Y-m-d')]);
        $byMonth = [];

        foreach ($stmt->fetchAll() as $row) {
            $byMonth[substr((string) $row['fecha'], 0, 7)] = $row;
        }

        $result = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->modify('+' . $i . ' months');
            $key = $month->format('Y-m');
            $row = $byMonth[$key] ?? null;
            $result[] = [
                'month' => $key,
                'fecha' => $row['fecha'] ?? null,
                'score' => $row !== null ? (int) $row['score'] : 0,
                'has_data' => $row !== null,
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $calculation
     */
    private function writeSnapshot(array $user, string $date, array $calculation): void
    {
        $payload = json_encode([
            'score' => $calculation['score'],
            'componentes' => $calculation['components'],
            'ingreso_centavos' => $calculation['income_centavos'],
            'ingreso_mensual_centavos' => $calculation['monthly_income_centavos'],
            'patrimonio_neto_centavos' => $calculation['net_worth_centavos'],
        ], JSON_UNESCAPED_UNICODE);

        $stmt = $this->db->prepare(
            'INSERT INTO score_snapshots
                (user_id, fecha, score, desglose_json, created_at, updated_at)
             VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                score = VALUES(score),
                desglose_json = VALUES(desglose_json),
                updated_at = UTC_TIMESTAMP()'
        );
        $stmt->execute([
            (int) $user['id'],
            $date,
            (int) $calculation['score'],
            is_string($payload) ? $payload : '{}',
        ]);
    }

    private function previousScore(int $userId, string $date): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT score FROM score_snapshots
             WHERE user_id = ? AND fecha < ?
             ORDER BY fecha DESC, id DESC LIMIT 1'
        );
        $stmt->execute([$userId, $date]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (int) $value;
    }

    /**
     * @param array<string, mixed> $period
     */
    private function previousNetWorth(int $userId, array $period): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT neto_centavos, neto_aportado_centavos, moneda, fecha FROM patrimonio_snapshots
             WHERE user_id = ? AND fecha < ?
             ORDER BY fecha DESC, id DESC LIMIT 1'
        );
        $stmt->execute([$userId, (new DateTimeImmutable($period['inicio'], new DateTimeZone('UTC')))->format('Y-m-01')]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        $net = (int) ($row['neto_aportado_centavos'] ?? $row['neto_centavos']);
        $user = (new \Cauce\Repositories\UserRepository())->findById($userId);

        if ($user !== null && (string) $row['moneda'] !== (string) ($user['moneda_activa'] ?? 'MXN')) {
            try {
                $net = (new CurrencyService())->convert(
                    $user,
                    $net,
                    (string) $row['moneda'],
                    (string) ($user['moneda_activa'] ?? 'MXN'),
                    substr((string) $row['fecha'], 0, 10)
                )['centavos'];
            } catch (\RuntimeException) {
                // Sin tasa: se usa el valor almacenado.
            }
        }

        return $net;
    }

    private function emergencySaved(int $userId): int
    {
        $repo = new SavingsRepository();
        $metas = $repo->metasForUser($userId);
        $emergencyId = 0;

        foreach ($metas as $meta) {
            if ((int) ($meta['es_emergencia'] ?? 0) === 1) {
                $emergencyId = (int) $meta['id'];
                break;
            }
        }

        if ($emergencyId === 0) {
            return 0;
        }

        $totals = $repo->totalsByMeta($userId);
        $row = $totals[$emergencyId] ?? [];

        return max(0, (int) ($row['aportado'] ?? 0) - (int) ($row['retirado'] ?? 0));
    }

    public static function savingsScore(int $netSavings, int $income): float
    {
        if ($income <= 0) {
            return 0.0;
        }

        return max(0.0, min(100.0, (($netSavings / $income) / 0.20) * 100));
    }

    public static function debtScore(int $balance, int $monthlyIncome): float
    {
        if ($monthlyIncome <= 0) {
            return 0.0;
        }

        $ratio = $balance / $monthlyIncome;

        return max(0.0, min(100.0, 100 - (($ratio / 0.50) * 100)));
    }

    public static function emergencyScore(int $saved, int $averageExpense): float
    {
        if ($averageExpense <= 0) {
            return 0.0;
        }

        $months = max(0, $saved) / $averageExpense;

        return max(0.0, min(100.0, ($months / 3) * 100));
    }

    public static function netWorthScore(int $netWorth, ?int $previousNetWorth): float
    {
        if ($netWorth <= 0) {
            return 0.0;
        }

        return $previousNetWorth !== null && $netWorth > $previousNetWorth ? 100.0 : 60.0;
    }

    public static function trend(?int $current, ?int $previous): ?string
    {
        if ($current === null || $previous === null) {
            return null;
        }

        return $current > $previous ? 'up' : ($current < $previous ? 'down' : 'same');
    }

    /**
     * @param array<string, mixed> $detail
     * @return array<string, mixed>
     */
    private function component(string $key, float $score, array $detail = []): array
    {
        $score = $this->clamp($score);
        $weight = self::WEIGHTS[$key];

        return [
            'key' => $key,
            'label' => __('score.components.' . $key),
            'weight' => $weight,
            'score' => round($score, 1),
            'points' => round($score * $weight / 100, 1),
            'detail' => $detail,
        ];
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }
}
