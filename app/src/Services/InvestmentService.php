<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Repositories\InvestmentRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Panel de inversión. Habla de aportado y acumulado, nunca de rendimiento.
 */
final class InvestmentService
{
    public const INSTRUMENTS = ['fondo', 'cripto', 'cetes', 'acciones', 'otro'];
    public const POSITION_TYPES = ['accion', 'etf', 'fibra', 'cripto', 'cetes', 'bono', 'sofipo', 'pagare', 'fondo', 'otro'];
    /** Prefijo de la clave de un tipo de inversión propio: `u<id>`. */
    private const CUSTOM_PREFIX = 'u';
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
        (new PortfolioService())->ensureMigrated($user);
        $status = $pauses->status($user);
        $consecutive = $pauses->consecutive($user);
        $byInstrument = $this->repo->totalsByInstrument((int) $user['id']);
        $custom = $this->customInstruments((int) $user['id']);

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
            'portfolios' => $portfolio['portfolios'],
            'portfolio_totals' => $portfolio['totals'],
            'yields' => (new PortfolioService())->recentYields($user, 15),
            'portfolio_names' => $this->portfolioNames((int) $user['id']),
            'position_names' => $this->positionNames($portfolio['positions']),
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
            'instrument_labels' => $custom,
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
     * Valor de mercado de cada posición activa (sin portafolios).
     *
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    public function valuePositions(array $user): array
    {
        $positions = $this->repo->positionsForUser((int) $user['id']);
        $principal = (string) ($user['moneda_activa'] ?? 'MXN');
        $converter = new CurrencyService();
        $rows = [];

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

        return $rows;
    }

    /**
     * Totales de inversión a partir de los portafolios.
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function portfolio(array $user): array
    {
        $summary = (new PortfolioService())->summary($user);
        $totals = $summary['totals'];
        $positions = [];

        foreach ($summary['portfolios'] as $group) {
            foreach ($group['instruments'] as $instrument) {
                $positions[] = $instrument;
            }
        }

        return [
            'positions' => $positions,
            'portfolios' => $summary['portfolios'],
            'totals' => $totals,
            'cost_centavos' => (int) $totals['instruments_cost'],
            'market_centavos' => (int) $totals['instruments_value'],
            'unassigned_centavos' => (int) $totals['cash'],
            'value_centavos' => (int) $totals['value'],
            'difference_centavos' => (int) $totals['instruments_value'] - (int) $totals['instruments_cost'],
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
            throw new RuntimeException('investment.errors.position_not_found');
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

        $types = self::POSITION_TYPES;
        $markets = ['BMV', 'EUA', 'CRIPTO', 'CETES', 'OTRO'];

        if ($name === '') {
            throw new InvalidArgumentException('investment.errors.position_name');
        }

        if (!in_array($type, $types, true) || !in_array($market, $markets, true)) {
            throw new InvalidArgumentException('investment.errors.position_type');
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException('investment.errors.position_quantity');
        }

        if ($cost < 0) {
            throw new InvalidArgumentException('investment.errors.position_cost');
        }

        if (!in_array($mode, ['automatico', 'manual', 'calculado'], true)) {
            throw new InvalidArgumentException('investment.errors.position_mode');
        }

        if ($valueDate !== '' && !$this->validPastDate($valueDate)) {
            throw new InvalidArgumentException('investment.errors.position_date');
        }

        if ($mode === 'automatico' && !in_array($market, ['BMV', 'EUA', 'CRIPTO'], true)) {
            throw new InvalidArgumentException('investment.errors.position_auto_market');
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
            throw new InvalidArgumentException('investment.errors.position_ticker');
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
        $extraFields = $this->positionExtras($user, $data, $existing);

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
                $valueDate,
                $extraFields
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
            $valueDate,
            $extraFields
        );

        if ((int) ($existing['portafolio_id'] ?? 0) !== $extraFields['portafolio_id']) {
            (new PortfolioService())->moveInstrument($user, $id, $extraFields['portafolio_id']);
        }

        return $id;
    }

    /**
     * Portafolio, tasa, vencimiento y notas de un instrumento.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $existing
     * @return array{portafolio_id: int, tasa_anual: ?string, fecha_vencimiento: ?string, notas: ?string}
     */
    private function positionExtras(array $user, array $data, ?array $existing): array
    {
        $portfolioId = (int) ($data['portafolio_id'] ?? 0);

        if ($portfolioId <= 0 && $existing !== null) {
            $portfolioId = (int) ($existing['portafolio_id'] ?? 0);
        }

        $portfolioId = (int) (new PortfolioService())->find($user, $portfolioId)['id'];
        $rate = trim(str_replace(',', '.', (string) ($data['tasa_anual'] ?? '')));

        if ($rate !== '' && (!is_numeric($rate) || (float) $rate < 0 || (float) $rate > 1000)) {
            throw new InvalidArgumentException('investment.errors.position_rate');
        }

        $maturity = trim((string) ($data['fecha_vencimiento'] ?? ''));

        if ($maturity !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $maturity);

            if ($date === false || $date->format('Y-m-d') !== $maturity) {
                throw new InvalidArgumentException('investment.errors.position_maturity');
            }
        }

        $notes = trim((string) ($data['notas'] ?? ''));

        return [
            'portafolio_id' => $portfolioId,
            'tasa_anual' => $rate !== '' ? number_format((float) $rate, 4, '.', '') : null,
            'fecha_vencimiento' => $maturity !== '' ? $maturity : null,
            'notas' => $notes !== '' ? mb_substr($notes, 0, 255) : null,
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    public function updatePositionValue(array $user, int $id, int $value, string $date): void
    {
        $position = $this->repo->findPosition($id, (int) $user['id']);

        if ($position === null) {
            throw new RuntimeException('investment.errors.position_not_found');
        }

        if ($value <= 0) {
            throw new InvalidArgumentException('investment.errors.position_value');
        }

        if (!$this->validPastDate($date)) {
            throw new InvalidArgumentException('investment.errors.position_date');
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
            throw new RuntimeException('investment.errors.position_not_found');
        }

        (new CoverService())->forget((int) $user['id'], 'posicion', $id);
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
     * Aporta a un portafolio y, opcionalmente, a uno de sus instrumentos.
     * Sin instrumento, el dinero queda como efectivo del portafolio.
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function contribute(
        array $user,
        int $amount,
        int $portfolioId,
        int $positionId,
        string $date,
        bool $extra,
        ?string $currency = null
    ): array {
        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero.');
        }

        [$portfolio, $position] = $this->target($user, $portfolioId, $positionId);
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
            $this->legacyKey($portfolio),
            $extra,
            $utc->format('Y-m-d H:i:s'),
            (int) $portfolio['id'],
            $position !== null ? (int) $position['id'] : null
        );

        return $this->status($user);
    }

    /**
     * Retira de un instrumento o del efectivo de un portafolio. Con motivo
     * «rebalanceo» registra además una aportación extra al destino.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function withdraw(array $user, int $amount, int $portfolioId, int $positionId, array $data, ?string $currency = null): array
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero.');
        }

        [$portfolio, $position] = $this->target($user, $portfolioId, $positionId);
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
        $principalAmount = (new CurrencyService())->toPrincipal($user, $amount, $currency, $utc->format('Y-m-d'))['centavos'];

        if ($principalAmount > $this->availableIn($user, (int) $portfolio['id'], $position !== null ? (int) $position['id'] : 0)) {
            throw new RuntimeException('No puedes retirar más de lo acumulado en ese instrumento.');
        }

        $rebalance = $reason === 'rebalanceo';
        $target = null;
        $targetPosition = null;

        if ($rebalance) {
            $targetPortfolioId = (int) ($data['destino_portafolio_id'] ?? 0);
            $targetPositionId = (int) ($data['destino_posicion_id'] ?? 0);

            try {
                [$target, $targetPosition] = $this->target($user, $targetPortfolioId, $targetPositionId);
            } catch (Throwable) {
                throw new InvalidArgumentException('Elige un instrumento de destino válido.');
            }

            if ((int) $target['id'] === (int) $portfolio['id']
                && ($targetPosition['id'] ?? 0) === ($position['id'] ?? 0)) {
                throw new InvalidArgumentException('El instrumento de destino debe ser distinto del origen.');
            }
        }

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
                $this->legacyKey($portfolio),
                $reason,
                $note,
                $utc->format('Y-m-d H:i:s'),
                (int) $portfolio['id'],
                $position !== null ? (int) $position['id'] : null
            );

            if ($rebalance && $target !== null) {
                $repo->createContribution(
                    (int) $user['id'],
                    $amount,
                    $currency,
                    $this->legacyKey($target),
                    true,
                    $utc->format('Y-m-d H:i:s'),
                    (int) $target['id'],
                    $targetPosition !== null ? (int) $targetPosition['id'] : null
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
            'target' => $rebalance ? (string) $target['nombre'] : null,
        ];
    }

    /**
     * Portafolio (obligatorio) e instrumento (opcional) del usuario.
     *
     * @param array<string, mixed> $user
     * @return array{0: array<string, mixed>, 1: array<string, mixed>|null}
     */
    private function target(array $user, int $portfolioId, int $positionId): array
    {
        $portfolio = (new PortfolioService())->find($user, $portfolioId);
        $position = null;

        if ($positionId > 0) {
            $position = $this->repo->findPosition($positionId, (int) $user['id']);

            if ($position === null || (int) ($position['portafolio_id'] ?? 0) !== (int) $portfolio['id']) {
                throw new InvalidArgumentException('investment.errors.position_not_found');
            }
        }

        return [$portfolio, $position];
    }

    /**
     * Lo que se puede retirar: el valor del instrumento o el efectivo del portafolio.
     *
     * @param array<string, mixed> $user
     */
    private function availableIn(array $user, int $portfolioId, int $positionId): int
    {
        foreach ((new PortfolioService())->summary($user)['portfolios'] as $group) {
            if ((int) $group['id'] !== $portfolioId) {
                continue;
            }

            if ($positionId === 0) {
                return (int) $group['cash'];
            }

            foreach ($group['instruments'] as $instrument) {
                if ((int) $instrument['id'] === $positionId) {
                    return (int) $instrument['valor_principal_centavos'];
                }
            }
        }

        return 0;
    }

    /**
     * Clave de `instrumento` para movimientos nuevos (compatibilidad con
     * pausas, exportación, búsqueda y patrimonio).
     *
     * @param array<string, mixed> $portfolio
     */
    private function legacyKey(array $portfolio): string
    {
        $legacy = (string) ($portfolio['instrumento_legado'] ?? '');

        return $legacy !== '' ? $legacy : PortfolioService::legacyForClass((string) ($portfolio['clase'] ?? 'otro'));
    }

    /**
     * @return array<int, string>
     */
    private function portfolioNames(int $userId): array
    {
        $names = [];

        foreach ((new \Cauce\Repositories\PortfolioRepository())->forUser($userId) as $row) {
            $names[(int) $row['id']] = (string) $row['nombre'];
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $positions
     * @return array<int, string>
     */
    private function positionNames(array $positions): array
    {
        $names = [];

        foreach ($positions as $position) {
            $names[(int) $position['id']] = (string) $position['nombre'];
        }

        return $names;
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

    /**
     * Tipos propios del usuario como mapa clave => nombre.
     *
     * @return array<string, string>
     */
    public function customInstruments(int $userId): array
    {
        $map = [];

        foreach ($this->repo->customInstruments($userId) as $row) {
            $map[self::CUSTOM_PREFIX . (int) $row['id']] = (string) $row['nombre'];
        }

        return $map;
    }

            private function validPastDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false
            && $date->format('Y-m-d') === $value
            && $value <= gmdate('Y-m-d', strtotime('+1 day'));
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
