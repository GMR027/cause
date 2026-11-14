<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Repositories\InvestmentRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Panel de inversión. Habla de aportado y acumulado, nunca de rendimiento.
 */
final class InvestmentService
{
    public const INSTRUMENTS = ['fondo', 'cripto', 'cetes', 'acciones', 'otro'];
    private const WITHDRAW_REASONS = ['liquidez', 'meta_cumplida', 'rebalanceo', 'otro'];

    private InvestmentRepository $repo;

    public function __construct(?InvestmentRepository $repo = null)
    {
        $this->repo = $repo ?? new InvestmentRepository();
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function panel(array $user): array
    {
        $support = new FinanceSupportService();
        $period = $support->period($user);
        $pauses = new PauseService();
        $pauses->refresh($user);
        $status = $pauses->status($user);
        $consecutive = $pauses->consecutive($user);
        $totals = $this->repo->totalsByInstrument((int) $user['id']);
        $byInstrument = [];

        foreach (self::INSTRUMENTS as $instrument) {
            $byInstrument[$instrument] = (int) ($totals[$instrument] ?? 0);
        }

        $contributions = $this->repo->recentContributions((int) $user['id'], 15);
        $withdrawals = $this->repo->recentWithdrawals((int) $user['id'], 15);
        $portfolio = $this->portfolio($user);

        foreach ($contributions as &$row) {
            $row['editable'] = $support->isCurrentPeriodDate((string) $row['fecha'], $user);
        }
        unset($row);

        foreach ($withdrawals as &$row) {
            $row['editable'] = $support->isCurrentPeriodDate((string) $row['fecha'], $user);
        }
        unset($row);

        return [
            'period' => $period,
            'total_centavos' => array_sum($byInstrument),
            'by_instrument' => $byInstrument,
            'last_contribution' => $this->repo->lastContribution((int) $user['id']),
            'contributions' => $contributions,
            'withdrawals' => $withdrawals,
            'positions' => $portfolio['positions'],
            'portfolio_value_centavos' => $portfolio['value_centavos'],
            'portfolio_cost_centavos' => $portfolio['cost_centavos'],
            'portfolio_market_centavos' => $portfolio['market_centavos'],
            'portfolio_unassigned_centavos' => $portfolio['unassigned_centavos'],
            'portfolio_difference_centavos' => $portfolio['difference_centavos'],
            'assigned_centavos' => $status['assigned'],
            'carry_centavos' => $status['carry'],
            'normal_centavos' => $status['normal'],
            'available_centavos' => $status['available'],
            'over_percentage' => $status['normal'] > ($status['assigned'] + $status['carry']),
            'paused' => $status['paused'],
            'pause' => $status['pause'],
            'consecutive' => $consecutive,
            'can_adjust' => (new FinanceSupportService())->categoryNamesByType($user, 'inversion') !== [],
            'instruments' => self::INSTRUMENTS,
            'withdraw_reasons' => self::WITHDRAW_REASONS,
            'currency' => (string) ($user['moneda_activa'] ?? 'MXN'),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function valuation(array $user): array
    {
        return $this->portfolio($user);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function portfolio(array $user): array
    {
        $positions = $this->repo->positionsForUser((int) $user['id']);
        $principal = (string) ($user['moneda_activa'] ?? 'MXN');
        $converter = new CurrencyService();
        $rows = [];
        $costTotal = 0;
        $marketTotal = 0;

        foreach ($positions as $position) {
            $currency = (string) ($position['moneda'] ?? 'MXN');
            $quantity = (float) ($position['cantidad'] ?? 0);
            $cost = (int) ($position['costo_total_principal_centavos'] ?? 0);

            if ($cost === 0 && (int) ($position['costo_total_centavos'] ?? 0) > 0) {
                try {
                    $cost = $converter->toPrincipal(
                        $user,
                        (int) $position['costo_total_centavos'],
                        $currency,
                        gmdate('Y-m-d')
                    )['centavos'];
                } catch (Throwable) {
                    $cost = (int) $position['costo_total_centavos'];
                }
            }

            $value = $cost;
            $valueNative = (int) ($position['valor_manual_centavos'] ?? 0);
            $price = null;
            $priceDate = null;
            $source = 'manual';
            $mode = (string) ($position['modo_valor'] ?? 'manual');
            $stale = $mode === 'automatico' && !is_numeric($position['ultimo_precio'] ?? null);

            if ($mode === 'automatico' && is_numeric($position['ultimo_precio'] ?? null)) {
                $price = (float) $position['ultimo_precio'];
                $priceDate = (string) ($position['precio_fecha'] ?? '');
                $priceCurrency = (string) ($position['precio_moneda'] ?? $currency);
                $valueNative = (int) round($quantity * $price * 100);
                $source = (string) ($position['precio_fuente'] ?? 'auto');

                try {
                    $value = $converter->convert($user, $valueNative, $priceCurrency, $principal, $priceDate !== '' ? $priceDate : gmdate('Y-m-d'))['centavos'];
                } catch (Throwable) {
                    $value = $cost;
                }

                $stale = $priceDate === '' || $priceDate < gmdate('Y-m-d', strtotime('-2 days'));
            } elseif ((int) ($position['valor_manual_principal_centavos'] ?? 0) > 0) {
                $value = (int) $position['valor_manual_principal_centavos'];
                $valueNative = (int) ($position['valor_manual_centavos'] ?? $value);
                $priceDate = (string) ($position['fecha_valor_manual'] ?? '');
            }

            $difference = $value - $cost;
            $costTotal += $cost;
            $marketTotal += $value;

            $rows[] = array_merge($position, [
                'valor_principal_centavos' => $value,
                'valor_nativo_centavos' => $valueNative,
                'costo_principal_centavos' => $cost,
                'diferencia_principal_centavos' => $difference,
                'diferencia_pct' => $cost > 0 ? round(($difference / $cost) * 100, 2) : 0.0,
                'precio_actual' => $price,
                'precio_fecha' => $priceDate,
                'fuente_precio' => $source,
                'desactualizado' => $stale,
            ]);
        }

        $contributed = $this->repo->sumContributions((int) $user['id'])
            - $this->repo->sumWithdrawals((int) $user['id']);
        $unassigned = max(0, $contributed - $costTotal);

        return [
            'positions' => $rows,
            'cost_centavos' => $costTotal,
            'market_centavos' => $marketTotal,
            'unassigned_centavos' => $unassigned,
            'value_centavos' => $marketTotal + $unassigned,
            'difference_centavos' => $marketTotal - $costTotal,
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     */
    public function createPosition(array $user, array $data): int
    {
        return $this->savePosition($user, null, $data);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     */
    public function updatePosition(array $user, int $id, array $data): void
    {
        $this->savePosition($user, $id, $data);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     */
    private function savePosition(array $user, ?int $id, array $data): int
    {
        $existing = $id !== null ? $this->repo->findPosition($id, (int) $user['id']) : null;

        if ($id !== null && $existing === null) {
            throw new RuntimeException('La posición no existe.');
        }

        $type = (string) ($data['tipo'] ?? 'otro');
        $market = strtoupper((string) ($data['mercado'] ?? 'OTRO'));
        $name = trim((string) ($data['nombre'] ?? ''));
        $ticker = strtoupper(trim((string) ($data['ticker'] ?? '')));
        $provider = trim((string) ($data['simbolo_proveedor'] ?? ''));
        $quantity = (float) ($data['cantidad'] ?? 0);
        $cost = (int) ($data['costo_total_centavos'] ?? 0);
        $mode = (string) ($data['modo_valor'] ?? 'manual');
        $manualValue = isset($data['valor_manual_centavos']) && (int) $data['valor_manual_centavos'] > 0
            ? (int) $data['valor_manual_centavos']
            : null;
        $valueDate = trim((string) ($data['fecha_valor'] ?? ''));
        $currencyInput = trim((string) ($data['moneda'] ?? ''));
        $currency = (new CurrencyService())->validateCode(
            $currencyInput !== '' ? $currencyInput : (string) ($user['moneda_activa'] ?? 'MXN')
        );

        $types = ['accion', 'etf', 'cripto', 'cetes', 'fondo', 'otro'];
        $markets = ['BMV', 'EUA', 'CRIPTO', 'CETES', 'OTRO'];

        if ($name === '') {
            throw new InvalidArgumentException('La posición necesita un nombre.');
        }

        if (!in_array($type, $types, true) || !in_array($market, $markets, true)) {
            throw new InvalidArgumentException('Tipo o mercado de inversión inválido.');
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor que cero.');
        }

        if ($cost < 0) {
            throw new InvalidArgumentException('El costo no puede ser negativo.');
        }

        if (!in_array($mode, ['automatico', 'manual'], true)) {
            throw new InvalidArgumentException('Modo de valor inválido.');
        }

        if ($mode === 'automatico' && !in_array($market, ['BMV', 'EUA', 'CRIPTO'], true)) {
            throw new InvalidArgumentException('Solo BMV, EUA y cripto pueden usar cotización automática.');
        }

        if ($market === 'BMV') {
            if ($provider === '') {
                $provider = $ticker;
            }
            $provider = strtoupper($provider);

            if ($provider !== '' && !str_ends_with($provider, '.MX')) {
                $provider .= '.MX';
            }
        } elseif ($market === 'EUA') {
            $provider = strtoupper($provider !== '' ? $provider : $ticker);
        } elseif ($market === 'CRIPTO') {
            $provider = strtolower($provider !== '' ? $provider : $ticker);
            $map = [
                'btc' => 'bitcoin',
                'eth' => 'ethereum',
                'usdt' => 'tether',
                'bnb' => 'binancecoin',
                'sol' => 'solana',
                'xrp' => 'ripple',
                'ada' => 'cardano',
                'doge' => 'dogecoin',
                'dot' => 'polkadot',
                'avax' => 'avalanche-2',
                'link' => 'chainlink',
                'ltc' => 'litecoin',
            ];
            $provider = $map[$provider] ?? $provider;
        } else {
            $provider = '';
        }

        if ($mode === 'automatico' && $provider === '') {
            throw new InvalidArgumentException('Escribe el ticker o símbolo para actualizar la cotización.');
        }

        $today = gmdate('Y-m-d');
        $costPrincipal = (new CurrencyService())->toPrincipal($user, $cost, $currency, $today)['centavos'];
        $manualPrincipal = null;

        if ($manualValue !== null) {
            $manualPrincipal = (new CurrencyService())->toPrincipal($user, $manualValue, $currency, $valueDate !== '' ? $valueDate : $today)['centavos'];
        } elseif ($mode === 'manual') {
            $manualValue = $cost;
            $manualPrincipal = $costPrincipal;
        }

        $valueDate = $valueDate !== '' ? $valueDate . ' 12:00:00' : null;

        if ($id === null) {
            return $this->repo->createPosition(
                (int) $user['id'],
                $type,
                $market,
                $ticker !== '' ? mb_substr($ticker, 0, 32) : null,
                $provider !== '' ? mb_substr($provider, 0, 64) : null,
                mb_substr($name, 0, 120),
                $currency,
                $quantity,
                $cost,
                $costPrincipal,
                $mode,
                $manualValue,
                $manualPrincipal,
                $valueDate
            );
        }

        $this->repo->updatePosition(
            $id,
            (int) $user['id'],
            $type,
            $market,
            $ticker !== '' ? mb_substr($ticker, 0, 32) : null,
            $provider !== '' ? mb_substr($provider, 0, 64) : null,
            mb_substr($name, 0, 120),
            $currency,
            $quantity,
            $cost,
            $costPrincipal,
            $mode,
            $manualValue,
            $manualPrincipal,
            $valueDate
        );

        return $id;
    }

    /**
     * @param array<string, mixed> $user
     */
    public function updatePositionValue(array $user, int $id, int $value, string $date): void
    {
        $position = $this->repo->findPosition($id, (int) $user['id']);

        if ($position === null) {
            throw new RuntimeException('La posición no existe.');
        }

        if ($value <= 0) {
            throw new InvalidArgumentException('El valor debe ser mayor que cero.');
        }

        $currency = (string) ($position['moneda'] ?? 'MXN');
        $principal = (new CurrencyService())->toPrincipal($user, $value, $currency, $date)['centavos'];
        $this->repo->updatePositionValue($id, (int) $user['id'], $value, $principal, $date . ' 12:00:00');
    }

    /**
     * @param array<string, mixed> $user
     */
    public function deletePosition(array $user, int $id): void
    {
        if ($this->repo->findPosition($id, (int) $user['id']) === null) {
            throw new RuntimeException('La posición no existe.');
        }

        $this->repo->deletePosition($id, (int) $user['id']);
    }

    /**
     * @param array<string, mixed> $user
     * @return array{updated: int, failed: int}
     */
    public function refreshQuotes(array $user): array
    {
        return (new QuoteService())->refreshUser($user);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function contribute(
        array $user,
        int $amount,
        string $instrument,
        string $date,
        bool $extra,
        ?string $currency = null
    ): array {
        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero.');
        }

        if (!in_array($instrument, self::INSTRUMENTS, true)) {
            throw new InvalidArgumentException('Instrumento inválido.');
        }

        $support = new FinanceSupportService();
        $utc = $support->toUtc($date, $user);
        $this->assertCurrent($user, $utc);

        if (!$extra && (new PauseService())->isPaused($user)) {
            throw new RuntimeException('La inversión está pausada en este período. Solo puedes hacer aportaciones extra.');
        }

        $currencyInput = trim((string) ($currency ?? ''));
        $currency = (new CurrencyService())->validateCode(
            $currencyInput !== '' ? $currencyInput : (string) ($user['moneda_activa'] ?? 'MXN')
        );
        $this->repo->createContribution(
            (int) $user['id'],
            $amount,
            $currency,
            $instrument,
            $extra,
            $utc->format('Y-m-d H:i:s')
        );

        return $this->status($user);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function withdraw(array $user, int $amount, string $instrument, array $data, ?string $currency = null): array
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero.');
        }

        if (!in_array($instrument, self::INSTRUMENTS, true)) {
            throw new InvalidArgumentException('Instrumento inválido.');
        }

        $reason = (string) ($data['motivo'] ?? '');

        if (!in_array($reason, self::WITHDRAW_REASONS, true)) {
            throw new InvalidArgumentException('Motivo de retiro inválido.');
        }

        $support = new FinanceSupportService();
        $utc = $support->toUtc((string) ($data['fecha'] ?? 'now'), $user);
        $this->assertCurrent($user, $utc);
        $currencyInput = trim((string) ($currency ?? ''));
        $currency = (new CurrencyService())->validateCode(
            $currencyInput !== '' ? $currencyInput : (string) ($user['moneda_activa'] ?? 'MXN')
        );
        $accumulated = $this->accumulated((int) $user['id'], $instrument);
        $principalAmount = (new CurrencyService())->toPrincipal($user, $amount, $currency, $utc->format('Y-m-d'))['centavos'];

        if ($principalAmount > $accumulated) {
            throw new RuntimeException('No puedes retirar más de lo acumulado en ese instrumento.');
        }

        $target = trim((string) ($data['destino_instrumento'] ?? ''));

        if ($reason === 'rebalanceo') {
            if (!in_array($target, self::INSTRUMENTS, true)) {
                throw new InvalidArgumentException('Elige un instrumento de destino válido.');
            }

            if ($target === $instrument) {
                throw new InvalidArgumentException('El instrumento de destino debe ser distinto del origen.');
            }
        }

        $rebalance = $reason === 'rebalanceo';

        $db = Database::connection();
        $note = isset($data['motivo_texto']) && trim((string) $data['motivo_texto']) !== ''
            ? mb_substr(trim((string) $data['motivo_texto']), 0, 255)
            : null;

        try {
            $db->beginTransaction();
            $repo = new InvestmentRepository($db);
            $repo->createWithdrawal(
                (int) $user['id'],
                $amount,
                $currency,
                $instrument,
                $reason,
                $note,
                $utc->format('Y-m-d H:i:s')
            );

            if ($rebalance) {
                $repo->createContribution(
                    (int) $user['id'],
                    $amount,
                    $currency,
                    $target,
                    true,
                    $utc->format('Y-m-d H:i:s')
                );
            }

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        return [
            'rebalanced' => $rebalance,
            'target' => $rebalance ? $target : null,
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    public function pause(array $user, string $reason, ?string $text): void
    {
        (new PauseService())->pause($user, $reason, $text);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function adjustPercentage(array $user, float $percent): void
    {
        if (!is_finite($percent) || $percent < 0 || $percent > 100) {
            throw new InvalidArgumentException('El porcentaje no es válido.');
        }

        (new MethodService())->adjustInvestment($user, $percent);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function updateContribution(array $user, int $id, int $amount, bool $extra, string $date): void
    {
        $row = $this->repo->findContribution($id, (int) $user['id']);
        $support = new FinanceSupportService();

        if ($row === null) {
            throw new RuntimeException('Ese movimiento no existe.');
        }

        if (!$support->isCurrentPeriodDate((string) $row['fecha'], $user)) {
            throw new RuntimeException('Ese movimiento ya no se puede editar.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero.');
        }

        $utc = $support->toUtc($date, $user);
        $this->assertCurrent($user, $utc);
        $instrument = (string) $row['instrumento'];
        $contributions = $this->repo->sumContributions((int) $user['id'], $instrument, null, null, null, $id);
        $withdrawals = $this->repo->sumWithdrawals((int) $user['id'], $instrument);

        if ($contributions + $amount - $withdrawals < 0) {
            throw new RuntimeException('El ajuste dejaría el instrumento en negativo.');
        }

        $this->repo->updateContribution($id, (int) $user['id'], $amount, $extra, $utc->format('Y-m-d H:i:s'));
    }

    /**
     * @param array<string, mixed> $user
     */
    public function deleteContribution(array $user, int $id): void
    {
        $row = $this->repo->findContribution($id, (int) $user['id']);
        $support = new FinanceSupportService();

        if ($row === null) {
            throw new RuntimeException('Ese movimiento no existe.');
        }

        if (!$support->isCurrentPeriodDate((string) $row['fecha'], $user)) {
            throw new RuntimeException('Ese movimiento ya no se puede borrar.');
        }

        $instrument = (string) $row['instrumento'];
        $contributions = $this->repo->sumContributions((int) $user['id'], $instrument, null, null, null, $id);
        $withdrawals = $this->repo->sumWithdrawals((int) $user['id'], $instrument);

        if ($contributions - $withdrawals < 0) {
            throw new RuntimeException('El ajuste dejaría el instrumento en negativo.');
        }

        $this->repo->deleteContribution($id, (int) $user['id']);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     */
    public function updateWithdrawal(array $user, int $id, int $amount, array $data): void
    {
        $row = $this->repo->findWithdrawal($id, (int) $user['id']);
        $support = new FinanceSupportService();

        if ($row === null) {
            throw new RuntimeException('Ese movimiento no existe.');
        }

        if (!$support->isCurrentPeriodDate((string) $row['fecha'], $user)) {
            throw new RuntimeException('Ese movimiento ya no se puede editar.');
        }

        $reason = (string) ($data['motivo'] ?? '');

        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero.');
        }

        if (!in_array($reason, self::WITHDRAW_REASONS, true)) {
            throw new InvalidArgumentException('Motivo de retiro inválido.');
        }

        $utc = $support->toUtc((string) ($data['fecha'] ?? 'now'), $user);
        $this->assertCurrent($user, $utc);
        $instrument = (string) $row['instrumento'];
        $contributions = $this->repo->sumContributions((int) $user['id'], $instrument);
        $withdrawals = $this->repo->sumWithdrawals((int) $user['id'], $instrument, null, null, $id);

        if ($withdrawals + $amount > $contributions) {
            throw new RuntimeException('El retiro supera el acumulado del instrumento.');
        }

        $note = isset($data['motivo_texto']) && trim((string) $data['motivo_texto']) !== ''
            ? mb_substr(trim((string) $data['motivo_texto']), 0, 255)
            : null;

        $this->repo->updateWithdrawal($id, (int) $user['id'], $amount, $reason, $note, $utc->format('Y-m-d H:i:s'));
    }

    /**
     * @param array<string, mixed> $user
     */
    public function deleteWithdrawal(array $user, int $id): void
    {
        $row = $this->repo->findWithdrawal($id, (int) $user['id']);
        $support = new FinanceSupportService();

        if ($row === null) {
            throw new RuntimeException('Ese movimiento no existe.');
        }

        if (!$support->isCurrentPeriodDate((string) $row['fecha'], $user)) {
            throw new RuntimeException('Ese movimiento ya no se puede borrar.');
        }

        $this->repo->deleteWithdrawal($id, (int) $user['id']);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function status(array $user): array
    {
        $status = (new PauseService())->status($user);

        return [
            'assigned_centavos' => $status['assigned'],
            'carry_centavos' => $status['carry'],
            'normal_centavos' => $status['normal'],
            'available_centavos' => $status['available'],
            'over_percentage' => $status['normal'] > ($status['assigned'] + $status['carry']),
        ];
    }

    private function accumulated(int $userId, string $instrument): int
    {
        return $this->repo->sumContributions($userId, $instrument)
            - $this->repo->sumWithdrawals($userId, $instrument);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function assertCurrent(array $user, \DateTimeImmutable $date): void
    {
        $period = (new FinanceSupportService())->period($user);

        if ($date < $period['inicio_dt'] || $date > $period['fin_dt']) {
            throw new RuntimeException('Solo puedes registrar movimientos en el período actual.');
        }
    }
}
