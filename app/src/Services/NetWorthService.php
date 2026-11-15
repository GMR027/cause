<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Repositories\DebtRepository;
use Cauce\Repositories\InvestmentRepository;
use Cauce\Repositories\SavingsRepository;
use Cauce\Repositories\UserRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Patrimonio neto y snapshots mensuales.
 */
final class NetWorthService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function summary(array $user): array
    {
        $userId = (int) $user['id'];
        $savingsRepo = new SavingsRepository();
        $investmentRepo = new InvestmentRepository();
        $debtRepo = new DebtRepository();
        $savings = $savingsRepo->sumAllContributions($userId) - $savingsRepo->sumAllWithdrawals($userId);
        $investmentContributed = $investmentRepo->sumContributions($userId) - $investmentRepo->sumWithdrawals($userId);
        $valuation = (new InvestmentService())->valuation($user);
        $investments = (int) ($valuation['value_centavos'] ?? $investmentContributed);
        $cash = max(0, (int) ($user['efectivo_manual_centavos'] ?? 0));
        $assets = $savings + $investments + $cash;
        $assetsContributed = $savings + $investmentContributed + $cash;
        $liabilities = $debtRepo->sumActiveBalance($userId);

        return [
            'savings_centavos' => $savings,
            'investment_centavos' => $investments,
            'investment_market_centavos' => (int) ($valuation['market_centavos'] ?? $investmentContributed),
            'investment_contributed_centavos' => $investmentContributed,
            'cash_centavos' => $cash,
            'assets_centavos' => $assets,
            'assets_contributed_centavos' => $assetsContributed,
            'liabilities_centavos' => $liabilities,
            'net_centavos' => $assets - $liabilities,
            'net_contributed_centavos' => $assetsContributed - $liabilities,
            'currency' => (string) ($user['moneda_activa'] ?? 'MXN'),
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    public function updateCash(array $user, ?int $cents): void
    {
        if ($cents !== null && ($cents < 0 || $cents > (int) config('ingreso.max_amount_cents', 1000000000000))) {
            throw new \InvalidArgumentException('Monto inválido.');
        }

        (new UserRepository())->updateCash((int) $user['id'], $cents);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function ensureCurrentSnapshot(array $user): void
    {
        // Un punto por día: la gráfica de Reportes se ve como la de una acción.
        $timezone = (new PeriodoService())->userTimezone($user);
        $date = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
        $this->writeSnapshot($user, $date);
    }

    /** Rangos de la gráfica de patrimonio y cuántos meses abarca cada uno. */
    public const RANGES = ['1m' => 1, '3m' => 3, '6m' => 6, '1a' => 12, 'todo' => null];

    /**
     * Serie diaria del patrimonio neto para el rango pedido, en moneda activa.
     *
     * @param array<string, mixed> $user
     * @return list<array{fecha: string, activos_centavos: int, pasivos_centavos: int, neto_centavos: int}>
     */
    public function series(array $user, string $range = '6m'): array
    {
        $this->ensureCurrentSnapshot($user);
        $months = array_key_exists($range, self::RANGES) ? self::RANGES[$range] : 6;
        $timezone = (new PeriodoService())->userTimezone($user);
        $sql = 'SELECT * FROM patrimonio_snapshots WHERE user_id = ?';
        $params = [(int) $user['id']];

        if ($months !== null) {
            $sql .= ' AND fecha >= ?';
            $params[] = (new DateTimeImmutable('today', $timezone))->modify('-' . $months . ' months')->format('Y-m-d');
        }

        $stmt = $this->db->prepare($sql . ' ORDER BY fecha ASC, id ASC');
        $stmt->execute($params);
        $result = [];

        foreach ($stmt->fetchAll() as $row) {
            [$assets, $liabilities, $net] = $this->convertRow($user, $row);
            $result[] = [
                'fecha' => substr((string) $row['fecha'], 0, 10),
                'activos_centavos' => $assets,
                'pasivos_centavos' => $liabilities,
                'neto_centavos' => $net,
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $row
     * @return array{0: int, 1: int, 2: int}
     */
    private function convertRow(array $user, array $row): array
    {
        $assets = (int) $row['activos_centavos'];
        $liabilities = (int) $row['pasivos_centavos'];
        $net = (int) $row['neto_centavos'];
        $active = (string) ($user['moneda_activa'] ?? 'MXN');

        if ((string) ($row['moneda'] ?? 'MXN') !== $active) {
            try {
                $converter = new CurrencyService();
                $date = substr((string) $row['fecha'], 0, 10);
                $assets = $converter->convert($user, $assets, (string) $row['moneda'], $active, $date)['centavos'];
                $liabilities = $converter->convert($user, $liabilities, (string) $row['moneda'], $active, $date)['centavos'];
                $net = $converter->convert($user, $net, (string) $row['moneda'], $active, $date)['centavos'];
            } catch (\RuntimeException) {
                // Sin tasa: se conserva el valor original del snapshot.
            }
        }

        return [$assets, $liabilities, $net];
    }

    /**
     * @param array<string, mixed> $user
     */
    public function snapshotForPeriod(array $user, string $periodStartUtc): void
    {
        $timezone = (new PeriodoService())->userTimezone($user);
        $date = (new DateTimeImmutable($periodStartUtc, new DateTimeZone('UTC')))
            ->setTimezone($timezone)
            ->format('Y-m-01');
        $this->writeSnapshot($user, $date);
    }

    /**
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    public function history(array $user, int $months = 12): array
    {
        $this->ensureCurrentSnapshot($user);
        $timezone = (new PeriodoService())->userTimezone($user);
        $current = new DateTimeImmutable('first day of this month', $timezone);
        $start = $current->modify('-' . max(0, $months - 1) . ' months');
        $stmt = $this->db->prepare(
            'SELECT * FROM patrimonio_snapshots
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
            // Con snapshots diarios, cada mes toma su último punto.
            $row = $byMonth[$key] ?? null;
            [$assets, $liabilities, $net] = $row !== null ? $this->convertRow($user, $row) : [0, 0, 0];

            $result[] = [
                'month' => $key,
                'label' => $month->format('Y-m'),
                'fecha' => $row['fecha'] ?? null,
                'activos_centavos' => $assets,
                'pasivos_centavos' => $liabilities,
                'neto_centavos' => $net,
                'has_data' => $row !== null,
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function writeSnapshot(array $user, string $date): void
    {
        $summary = $this->summary($user);
        $stmt = $this->db->prepare(
            'INSERT INTO patrimonio_snapshots
                (user_id, fecha, activos_centavos, pasivos_centavos, neto_centavos,
                 neto_aportado_centavos, moneda, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                activos_centavos = VALUES(activos_centavos),
                pasivos_centavos = VALUES(pasivos_centavos),
                neto_centavos = VALUES(neto_centavos),
                neto_aportado_centavos = VALUES(neto_aportado_centavos),
                moneda = VALUES(moneda),
                updated_at = UTC_TIMESTAMP()'
        );
        $stmt->execute([
            (int) $user['id'],
            $date,
            $summary['assets_centavos'],
            $summary['liabilities_centavos'],
            $summary['net_centavos'],
            $summary['net_contributed_centavos'],
            $summary['currency'],
        ]);
    }
}
