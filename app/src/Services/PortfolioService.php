<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\PortfolioRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Portafolio → Instrumentos → Movimientos.
 *
 * Valor de un instrumento según su modo:
 * - automatico: cantidad × cotización.
 * - manual: último valor capturado + lo que pasó después (aportes, retiros,
 *   rendimientos reinvertidos, bajas y comisiones).
 * - calculado: costo + rendimientos reinvertidos − bajas − comisiones.
 *
 * El costo de un instrumento es su costo inicial declarado más lo aportado y
 * menos lo retirado a él. Lo aportado al portafolio sin instrumento es su
 * efectivo; el costo inicial de los instrumentos sale de ese efectivo (igual
 * que el antiguo «aportado sin asignar»).
 */
final class PortfolioService
{
    public const CLASSES = ['sofipo', 'acciones', 'fibras', 'cetes_bonos', 'fondos', 'cripto', 'otro'];
    public const YIELD_TYPES = ['interes', 'dividendo', 'renta', 'baja', 'comision'];
    public const INCOME_TYPES = ['interes', 'dividendo', 'renta'];
    public const DESTINATIONS = ['reinvertido', 'pagado'];
    public const NAME_MAX = 80;

    /** Clave antigua de `instrumento` que se guarda en movimientos de cada clase. */
    private const CLASS_TO_LEGACY = [
        'sofipo' => 'otro',
        'acciones' => 'acciones',
        'fibras' => 'otro',
        'cetes_bonos' => 'cetes',
        'fondos' => 'fondo',
        'cripto' => 'cripto',
        'otro' => 'otro',
    ];

    /** Tipo de posición → clave antigua del tipo de inversión. */
    private const POSITION_TO_LEGACY = [
        'accion' => 'acciones',
        'etf' => 'acciones',
        'cripto' => 'cripto',
        'cetes' => 'cetes',
        'bono' => 'cetes',
        'fondo' => 'fondo',
        'sofipo' => 'otro',
        'pagare' => 'otro',
        'fibra' => 'otro',
        'otro' => 'otro',
    ];

    private PortfolioRepository $repo;

    public function __construct(?PortfolioRepository $repo = null)
    {
        $this->repo = $repo ?? new PortfolioRepository();
    }

    public static function classForLegacy(string $key): string
    {
        return match ($key) {
            'acciones' => 'acciones',
            'cetes' => 'cetes_bonos',
            'fondo' => 'fondos',
            'cripto' => 'cripto',
            default => 'otro',
        };
    }

    public static function legacyForClass(string $class): string
    {
        return self::CLASS_TO_LEGACY[$class] ?? 'otro';
    }

    public static function legacyForPositionType(string $type): string
    {
        return self::POSITION_TO_LEGACY[$type] ?? 'otro';
    }

    /**
     * Convierte los tipos de inversión antiguos en portafolios la primera vez.
     * Idempotente: si ya hay portafolios no hace nada.
     *
     * @param array<string, mixed> $user
     */
    public function ensureMigrated(array $user): void
    {
        $userId = (int) $user['id'];

        if ($this->repo->count($userId) > 0) {
            return;
        }

        $custom = (new InvestmentService())->customInstruments($userId);
        $keys = $this->repo->legacyKeys($userId);
        $positionTypes = $this->repo->unassignedPositionTypes($userId);

        foreach ($positionTypes as $type) {
            $keys[] = self::legacyForPositionType($type);
        }

        $keys = array_values(array_unique(array_merge($keys, array_keys($custom))));

        if ($keys === []) {
            return;
        }

        $db = $this->repo->connection();

        try {
            $db->beginTransaction();
            $used = [];

            foreach ($keys as $key) {
                $name = (string) ($custom[$key] ?? __('investment.instruments.' . $key));

                if ($name === '' || str_starts_with($name, 'investment.')) {
                    $name = $key;
                }

                $base = mb_substr($name, 0, self::NAME_MAX);
                $name = $base;
                $suffix = 2;

                while (isset($used[mb_strtolower($name)])) {
                    $name = mb_substr($base, 0, self::NAME_MAX - 4) . ' ' . $suffix++;
                }

                $used[mb_strtolower($name)] = true;
                $id = $this->repo->create($userId, $name, self::classForLegacy($key), null, $key);
                $this->repo->assignLegacyMovements($userId, $key, $id);
                $this->repo->assignPositionsByType(
                    $userId,
                    array_values(array_filter(
                        array_keys(self::POSITION_TO_LEGACY),
                        static fn (string $type): bool => self::POSITION_TO_LEGACY[$type] === $key
                    )),
                    $id
                );
            }

            $db->commit();
        } catch (PDOException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            // Otra petición migró al mismo tiempo: sus portafolios ya existen.
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Portafolios con instrumentos, efectivo y totales.
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function summary(array $user): array
    {
        $userId = (int) $user['id'];
        $timezone = (new PeriodoService())->userTimezone($user);
        $yearStart = (new DateTimeImmutable('first day of january this year 00:00', $timezone))
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        return self::build(
            $this->repo->forUser($userId),
            (new InvestmentService())->valuePositions($user),
            $this->repo->movements($userId),
            $this->repo->yields($userId),
            $yearStart
        );
    }

    /**
     * Cálculo puro (sin BD) de valores por instrumento y portafolio.
     *
     * @param list<array<string, mixed>> $portfolios
     * @param list<array<string, mixed>> $positions  filas de InvestmentService::valuePositions
     * @param list<array<string, mixed>> $movements  kind in|out, portafolio_id, posicion_id, instrumento, monto, fecha
     * @param list<array<string, mixed>> $yields     portafolio_id, posicion_id, tipo, destino, monto, fecha
     * @return array<string, mixed>
     */
    public static function build(array $portfolios, array $positions, array $movements, array $yields, string $yearStart): array
    {
        $groups = [];
        $byLegacy = [];

        foreach ($portfolios as $portfolio) {
            $id = (int) $portfolio['id'];
            $groups[$id] = self::emptyGroup($portfolio);
            $legacy = (string) ($portfolio['instrumento_legado'] ?? '');

            if ($legacy !== '' && !isset($byLegacy[$legacy])) {
                $byLegacy[$legacy] = $id;
            }
        }

        $resolve = static function (mixed $portfolioId, string $legacy) use (&$groups, $byLegacy): int {
            $id = (int) ($portfolioId ?? 0);

            if ($id > 0 && isset($groups[$id])) {
                return $id;
            }

            $id = $byLegacy[$legacy] ?? 0;

            if ($id === 0 && !isset($groups[0])) {
                $groups[0] = self::emptyGroup(['id' => 0, 'nombre' => '', 'clase' => 'otro', 'institucion' => null]);
            }

            return $id;
        };

        $instruments = [];
        $positionGroup = [];

        foreach ($positions as $position) {
            $pid = (int) $position['id'];
            $gid = $resolve($position['portafolio_id'] ?? null, self::legacyForPositionType((string) ($position['tipo'] ?? 'otro')));
            $positionGroup[$pid] = $gid;
            $instruments[$pid] = [
                'row' => $position,
                'base' => (int) ($position['costo_principal_centavos'] ?? 0),
                'in' => 0,
                'out' => 0,
                'reinvested' => 0,
                'paid' => 0,
                'losses' => 0,
                'after' => 0,
            ];
        }

        $cashNet = [];
        $cashYield = [];
        $cashPaid = [];
        $contributed = [];
        $yieldsYear = [];

        foreach ($movements as $move) {
            $amount = (int) ($move['monto'] ?? 0);
            $sign = ($move['kind'] ?? 'in') === 'out' ? -1 : 1;
            $pid = (int) ($move['posicion_id'] ?? 0);

            if ($pid > 0 && isset($instruments[$pid])) {
                $gid = $positionGroup[$pid];
                $instruments[$pid][$sign > 0 ? 'in' : 'out'] += $amount;

                if (self::isAfterManual($instruments[$pid]['row'], (string) ($move['fecha'] ?? ''))) {
                    $instruments[$pid]['after'] += $sign * $amount;
                }
            } else {
                $gid = $resolve($move['portafolio_id'] ?? null, (string) ($move['instrumento'] ?? 'otro'));
                $cashNet[$gid] = ($cashNet[$gid] ?? 0) + $sign * $amount;
            }

            $contributed[$gid] = ($contributed[$gid] ?? 0) + $sign * $amount;
        }

        foreach ($yields as $yield) {
            $amount = (int) ($yield['monto'] ?? 0);
            $type = (string) ($yield['tipo'] ?? 'interes');
            $isIncome = in_array($type, self::INCOME_TYPES, true);
            $paid = $isIncome && ($yield['destino'] ?? 'reinvertido') === 'pagado';
            $effect = $paid ? 0 : ($isIncome ? $amount : -$amount);
            $pid = (int) ($yield['posicion_id'] ?? 0);

            if ($pid > 0 && isset($instruments[$pid])) {
                $gid = $positionGroup[$pid];

                if ($paid) {
                    $instruments[$pid]['paid'] += $amount;
                } elseif ($isIncome) {
                    $instruments[$pid]['reinvested'] += $amount;
                } else {
                    $instruments[$pid]['losses'] += $amount;
                }

                if (self::isAfterManual($instruments[$pid]['row'], (string) ($yield['fecha'] ?? ''))) {
                    $instruments[$pid]['after'] += $effect;
                }
            } else {
                $gid = $resolve($yield['portafolio_id'] ?? null, '');

                if ($paid) {
                    $cashPaid[$gid] = ($cashPaid[$gid] ?? 0) + $amount;
                } else {
                    $cashYield[$gid] = ($cashYield[$gid] ?? 0) + $effect;
                }
            }

            if ($isIncome && (string) ($yield['fecha'] ?? '') >= $yearStart) {
                $yieldsYear[$gid] = ($yieldsYear[$gid] ?? 0) + $amount;
            }
        }

        foreach ($instruments as $pid => $data) {
            $gid = $positionGroup[$pid];
            $row = $data['row'];
            $cost = $data['base'] + $data['in'] - $data['out'];
            $mode = (string) ($row['modo_valor'] ?? 'manual');
            $value = match ($mode) {
                'automatico' => ($row['precio_actual'] ?? null) !== null ? (int) ($row['valor_principal_centavos'] ?? $cost) : $cost,
                'calculado' => $cost + $data['reinvested'] - $data['losses'],
                default => self::manualBase($row, $data['base']) + $data['after'],
            };
            $value = max(0, $value);
            $gain = $value - $cost + $data['paid'];

            $groups[$gid]['instruments'][] = array_merge($row, [
                'valor_principal_centavos' => $value,
                'costo_principal_centavos' => $cost,
                'aportado_centavos' => $data['in'],
                'retirado_centavos' => $data['out'],
                'reinvertido_centavos' => $data['reinvested'],
                'pagado_centavos' => $data['paid'],
                'bajas_centavos' => $data['losses'],
                'ganancia_centavos' => $gain,
                'ganancia_pct' => $cost > 0 ? round($gain / $cost * 100, 2) : 0.0,
                'diferencia_principal_centavos' => $gain,
                'diferencia_pct' => $cost > 0 ? round($gain / $cost * 100, 2) : 0.0,
            ]);
            $groups[$gid]['instruments_value'] += $value;
            $groups[$gid]['instruments_cost'] += $cost;
            $groups[$gid]['legacy_cost'] += $data['base'];
            $groups[$gid]['gain'] += $gain;
        }

        $totals = ['value' => 0, 'invested' => 0, 'gain' => 0, 'cash' => 0, 'contributed' => 0, 'yields_year' => 0,
            'instruments_value' => 0, 'instruments_cost' => 0];

        foreach ($groups as $gid => &$group) {
            $baseCash = max(0, ($cashNet[$gid] ?? 0) - $group['legacy_cost']);
            $cash = max(0, $baseCash + ($cashYield[$gid] ?? 0));
            $group['cash'] = $cash;
            $group['value'] = $group['instruments_value'] + $cash;
            $group['gain'] += ($cash - $baseCash) + ($cashPaid[$gid] ?? 0);
            $group['invested'] = $group['instruments_cost'] + $baseCash;
            $group['gain_pct'] = $group['invested'] > 0 ? round($group['gain'] / $group['invested'] * 100, 2) : 0.0;
            $group['contributed'] = $contributed[$gid] ?? 0;
            $group['yields_year'] = $yieldsYear[$gid] ?? 0;

            foreach (['value', 'invested', 'gain', 'cash', 'contributed', 'yields_year', 'instruments_value', 'instruments_cost'] as $key) {
                $totals[$key] += $group[$key];
            }
        }
        unset($group);

        // El grupo virtual «sin portafolio» solo se muestra si tiene algo.
        if (isset($groups[0]) && $groups[0]['instruments'] === [] && $groups[0]['value'] === 0 && $groups[0]['contributed'] === 0) {
            unset($groups[0]);
        }

        $totals['gain_pct'] = $totals['invested'] > 0 ? round($totals['gain'] / $totals['invested'] * 100, 2) : 0.0;

        return [
            'portfolios' => array_values($groups),
            'totals' => $totals,
        ];
    }

    /**
     * @param array<string, mixed> $portfolio
     * @return array<string, mixed>
     */
    private static function emptyGroup(array $portfolio): array
    {
        return [
            'id' => (int) $portfolio['id'],
            'nombre' => (string) ($portfolio['nombre'] ?? ''),
            'clase' => (string) ($portfolio['clase'] ?? 'otro'),
            'institucion' => $portfolio['institucion'] ?? null,
            'instruments' => [],
            'instruments_value' => 0,
            'instruments_cost' => 0,
            'legacy_cost' => 0,
            'gain' => 0,
            'cash' => 0,
            'value' => 0,
            'invested' => 0,
            'gain_pct' => 0.0,
            'contributed' => 0,
            'yields_year' => 0,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function manualBase(array $row, int $base): int
    {
        $manual = (int) ($row['valor_manual_principal_centavos'] ?? 0);

        return $manual > 0 ? $manual : $base;
    }

    /**
     * En modo manual, cuenta lo ocurrido después de la fecha del último valor.
     *
     * @param array<string, mixed> $row
     */
    private static function isAfterManual(array $row, string $date): bool
    {
        $valueDate = (string) ($row['fecha_valor_manual'] ?? '');

        return $valueDate === '' || substr($date, 0, 10) > substr($valueDate, 0, 10);
    }

    // ------------------------------------------------------------------
    // Portafolios
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $user
     */
    public function create(array $user, string $name, string $class, string $institution = ''): int
    {
        [$name, $class, $institution] = $this->validate((int) $user['id'], $name, $class, $institution, null);

        return $this->repo->create((int) $user['id'], $name, $class, $institution, null);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function update(array $user, int $id, string $name, string $class, string $institution = ''): void
    {
        $this->find($user, $id);
        [$name, $class, $institution] = $this->validate((int) $user['id'], $name, $class, $institution, $id);
        $this->repo->update($id, (int) $user['id'], $name, $class, $institution);
    }

    /**
     * Solo se borra un portafolio vacío: sin instrumentos ni movimientos.
     *
     * @param array<string, mixed> $user
     */
    public function delete(array $user, int $id): void
    {
        $this->find($user, $id);

        if ($this->repo->usage($id, (int) $user['id']) > 0) {
            throw new RuntimeException('investment.errors.portfolio_in_use');
        }

        $this->repo->delete($id, (int) $user['id']);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function find(array $user, int $id): array
    {
        $portfolio = $id > 0 ? $this->repo->find($id, (int) $user['id']) : null;

        if ($portfolio === null) {
            throw new RuntimeException('investment.errors.portfolio_not_found');
        }

        return $portfolio;
    }

    /**
     * @param array<string, mixed> $user
     */
    public function moveInstrument(array $user, int $positionId, int $portfolioId): void
    {
        $this->find($user, $portfolioId);

        if ((new \Cauce\Repositories\InvestmentRepository())->findPosition($positionId, (int) $user['id']) === null) {
            throw new RuntimeException('investment.errors.position_not_found');
        }

        $db = $this->repo->connection();

        try {
            $db->beginTransaction();
            $this->repo->movePosition($positionId, (int) $user['id'], $portfolioId);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return array{0: string, 1: string, 2: ?string}
     */
    private function validate(int $userId, string $name, string $class, string $institution, ?int $id): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', preg_replace('/[\p{C}]+/u', '', $name) ?? '') ?? '');
        $institution = trim(preg_replace('/[\p{C}]+/u', '', $institution) ?? '');

        if (mb_strlen($name) < 2 || mb_strlen($name) > self::NAME_MAX) {
            throw new InvalidArgumentException('investment.errors.portfolio_name');
        }

        if (!in_array($class, self::CLASSES, true)) {
            throw new InvalidArgumentException('investment.errors.portfolio_class');
        }

        $existing = $this->repo->findByName($userId, $name);

        if ($existing !== null && (int) $existing['id'] !== $id) {
            throw new InvalidArgumentException('investment.errors.portfolio_duplicate');
        }

        return [$name, $class, $institution !== '' ? mb_substr($institution, 0, 120) : null];
    }

    // ------------------------------------------------------------------
    // Rendimientos, bajas y comisiones
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data tipo, monto_centavos, moneda, fecha, destino, nota, portafolio_id, posicion_id
     * @return array{id: int, ingreso_id: ?int}
     */
    public function recordYield(array $user, array $data): array
    {
        $userId = (int) $user['id'];
        $portfolio = $this->find($user, (int) ($data['portafolio_id'] ?? 0));
        $positionId = (int) ($data['posicion_id'] ?? 0);

        if ($positionId > 0) {
            $position = (new \Cauce\Repositories\InvestmentRepository())->findPosition($positionId, $userId);

            if ($position === null || (int) ($position['portafolio_id'] ?? 0) !== (int) $portfolio['id']) {
                throw new InvalidArgumentException('investment.errors.position_not_found');
            }
        }

        $clean = $this->cleanYield($user, $data);
        $destination = (string) ($data['destino'] ?? 'reinvertido');

        if (!in_array($destination, self::DESTINATIONS, true)) {
            throw new InvalidArgumentException('investment.errors.yield_destination');
        }

        if (!in_array($clean['tipo'], self::INCOME_TYPES, true)) {
            $destination = 'reinvertido';
        }

        $incomeId = null;

        if ($destination === 'pagado') {
            // Crea el ingreso extra (pendiente de acreditar) en su propia transacción.
            $incomeId = (int) (new IngresoService())->crear($user, $clean['monto_centavos'], 'extra', $clean['moneda'])['ingreso_id'];
        }

        $id = $this->repo->createYield($userId, array_merge($clean, [
            'portafolio_id' => (int) $portfolio['id'],
            'posicion_id' => $positionId > 0 ? $positionId : null,
            'destino' => $destination,
            'ingreso_id' => $incomeId,
        ]));

        return ['id' => $id, 'ingreso_id' => $incomeId];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     */
    public function updateYield(array $user, int $id, array $data): void
    {
        $row = $this->editableYield($user, $id);
        $data['tipo'] = (string) ($data['tipo'] ?? $row['tipo']);
        $isIncome = in_array((string) $row['tipo'], self::INCOME_TYPES, true);

        // Un rendimiento pagado ya generó un ingreso: no cambia de naturaleza.
        if ((string) $row['destino'] === 'pagado' || $isIncome !== in_array($data['tipo'], self::INCOME_TYPES, true)) {
            $data['tipo'] = (string) $row['tipo'];
        }

        $clean = $this->cleanYield($user, $data);

        if ((string) $row['destino'] === 'pagado') {
            $clean['monto_centavos'] = (int) $row['monto_centavos'];
            $clean['moneda'] = (string) $row['moneda'];
            $clean['monto_principal_centavos'] = (int) ($row['monto_principal_centavos'] ?? $row['monto_centavos']);
        }

        $this->repo->updateYield($id, (int) $user['id'], $clean);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function deleteYield(array $user, int $id): void
    {
        $this->editableYield($user, $id);
        $this->repo->deleteYield($id, (int) $user['id']);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function editableYield(array $user, int $id): array
    {
        $row = $this->repo->findYield($id, (int) $user['id']);

        if ($row === null) {
            throw new RuntimeException('Ese movimiento no existe.');
        }

        if (!(new FinanceSupportService())->isCurrentPeriodDate((string) $row['fecha'], $user)) {
            throw new RuntimeException('Ese movimiento ya no se puede editar.');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function cleanYield(array $user, array $data): array
    {
        $type = (string) ($data['tipo'] ?? '');
        $amount = (int) ($data['monto_centavos'] ?? 0);

        if (!in_array($type, self::YIELD_TYPES, true)) {
            throw new InvalidArgumentException('investment.errors.yield_type');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero.');
        }

        $support = new FinanceSupportService();
        $utc = $support->toUtc((string) ($data['fecha'] ?? 'now'), $user);
        $period = $support->period($user);

        if ($utc < $period['inicio_dt'] || $utc > $period['fin_dt']) {
            throw new RuntimeException('Solo puedes registrar movimientos en el período actual.');
        }

        $currencyInput = trim((string) ($data['moneda'] ?? ''));
        $converter = new CurrencyService();
        $currency = $converter->validateCode($currencyInput !== '' ? $currencyInput : (string) ($user['moneda_activa'] ?? 'MXN'));
        $note = trim((string) ($data['nota'] ?? ''));

        return [
            'tipo' => $type,
            'monto_centavos' => $amount,
            'moneda' => $currency,
            'monto_principal_centavos' => $converter->toPrincipal($user, $amount, $currency, $utc->format('Y-m-d'))['centavos'],
            'fecha' => $utc->format('Y-m-d H:i:s'),
            'nota' => $note !== '' ? mb_substr($note, 0, 255) : null,
        ];
    }

    /**
     * Rendimientos recientes para el historial.
     *
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    public function recentYields(array $user, int $limit = 15): array
    {
        $support = new FinanceSupportService();
        $rows = array_slice($this->repo->yields((int) $user['id']), 0, max(1, $limit));

        foreach ($rows as &$row) {
            $row['editable'] = $support->isCurrentPeriodDate((string) $row['fecha'], $user);
        }
        unset($row);

        return $rows;
    }
}
