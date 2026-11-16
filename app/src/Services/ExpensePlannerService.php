<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\DebtRepository;
use Cauce\Repositories\GastoRepository;
use Cauce\Repositories\RecurringExpenseRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Cálculo del planeador de gastos.
 *
 * Toma el próximo ingreso esperado, lo reparte con el mismo motor que un
 * ingreso real y descuenta los compromisos de la ventana (hoy → fecha del
 * ingreso): gastos recurrentes, recordatorios y mínimos de deuda y gastos
 * pendientes. Los apartados del método (ahorro, inversión, donación y
 * compartir) se muestran como destinos, no como deuda adicional.
 */
final class ExpensePlannerService
{
    public const SECTION_SIN_SECCION = '__sin_seccion';

    private ?MethodService $methods = null;

    private ?RepartoService $reparto = null;

    private ?RecurringExpenseRepository $recurring = null;

    private ?DebtRepository $debts = null;

    private ?GastoRepository $gastos = null;

    private ?SavingsService $savings = null;

    private ?CurrencyService $currency = null;

    private ?PeriodoService $periods = null;

    private ?RecurrenceService $recurrence = null;

    private ?DebtService $debtService = null;

    /** @var array<string, mixed>|null */
    private ?array $methodCache = null;

    private bool $methodLoaded = false;

    public function __construct(
        ?MethodService $methods = null,
        ?RepartoService $reparto = null,
        ?RecurringExpenseRepository $recurring = null,
        ?DebtRepository $debts = null,
        ?GastoRepository $gastos = null,
        ?SavingsService $savings = null,
        ?CurrencyService $currency = null,
        ?PeriodoService $periods = null,
        ?RecurrenceService $recurrence = null,
        ?DebtService $debtService = null
    ) {
        $this->methods = $methods;
        $this->reparto = $reparto;
        $this->recurring = $recurring;
        $this->debts = $debts;
        $this->gastos = $gastos;
        $this->savings = $savings;
        $this->currency = $currency;
        $this->periods = $periods;
        $this->recurrence = $recurrence;
        $this->debtService = $debtService;
    }

    /**
     * Valida y normaliza la entrada del formulario.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     * @return array{
     *     errors: list<string>, warnings: list<array{key: string, params?: array<string, string>}>,
     *     fecha_ingreso: ?string, monto_centavos: int, moneda: string, notas: ?string
     * }
     */
    public function validate(array $user, array $input, ?DateTimeImmutable $now = null): array
    {
        $timezone = $this->periods()->userTimezone($user);
        $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $today = $now->setTimezone($timezone)->setTime(0, 0, 0);
        $errors = [];
        $warnings = [];

        $fechaRaw = trim((string) ($input['fecha_ingreso'] ?? $input['fecha'] ?? ''));
        $fecha = null;

        if ($fechaRaw === '') {
            $errors[] = 'planner.errors.date_required';
        } else {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaRaw, $timezone);
            $check = $parsed !== false ? $parsed->format('Y-m-d') : '';

            if ($check !== $fechaRaw) {
                $errors[] = 'planner.errors.date_invalid';
            } elseif ($parsed < $today) {
                $errors[] = 'planner.errors.date_past';
            } else {
                $days = (int) $today->diff($parsed)->days;

                if ($days > 366) {
                    $errors[] = 'planner.errors.date_far';
                } else {
                    $fecha = $parsed;
                }
            }
        }

        $principal = $this->principalOf($user);
        $moneda = strtoupper(trim((string) ($input['moneda'] ?? $principal)));

        if (!in_array($moneda, CurrencyService::SUPPORTED, true)) {
            $errors[] = 'planner.errors.currency_invalid';
            $moneda = $principal;
        }

        $montoRaw = trim((string) ($input['monto'] ?? ''));
        $monto = $montoRaw === '' ? null : parse_money_cents($montoRaw);

        if ($monto === null || $monto <= 0) {
            $errors[] = 'planner.errors.amount_invalid';
        }

        $notas = trim((string) ($input['notas'] ?? ''));
        $notas = $notas === '' ? null : mb_substr($notas, 0, 500);

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'fecha_ingreso' => $fecha?->format('Y-m-d'),
            'monto_centavos' => (int) ($monto ?? 0),
            'moneda' => $moneda,
            'notas' => $notas,
        ];
    }

    /**
     * Calcula un plan completo, con compromisos, secciones y conceptos.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     * @param list<array<string, mixed>>|null $items null precarga las metas de ahorro
     * @return array<string, mixed>
     */
    public function build(array $user, array $input, ?array $items = null, ?DateTimeImmutable $now = null): array
    {
        $principal = $this->principalOf($user);
        $validated = $this->validate($user, $input, $now);
        $errors = $validated['errors'];
        $warnings = $validated['warnings'];

        $result = [
            'errors' => $errors,
            'warnings' => $warnings,
            'fecha_ingreso' => $validated['fecha_ingreso'],
            'monto_centavos' => $validated['monto_centavos'],
            'monto_principal_centavos' => 0,
            'moneda' => $validated['moneda'],
            'principal' => $principal,
            'notas' => $validated['notas'],
            'dias_restantes' => 0,
            'method' => null,
            'metodo_id' => null,
            'metodo_codigo' => null,
            'metodo_nombre' => null,
            'porcentajes' => [],
            'sections' => [],
            'commitments' => [],
            'items' => [],
            'suggested_items' => [],
            'debts' => [],
            'summary' => $this->emptySummary(),
        ];

        $methodData = $this->activeMethod($user);

        if ($methodData === null) {
            $result['errors'][] = 'planner.errors.no_method';

            return $result;
        }

        $categories = $methodData['method']['categorias'];
        $percentages = $methodData['percentages'];
        $result['method'] = $methodData;
        $result['metodo_id'] = (int) $methodData['method']['id'];
        $result['metodo_codigo'] = (string) $methodData['method']['codigo'];
        $result['metodo_nombre'] = (string) $methodData['method']['nombre'];
        $result['porcentajes'] = $percentages;

        $timezone = $this->periods()->userTimezone($user);
        $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $today = $now->setTimezone($timezone)->setTime(0, 0, 0);
        $fechaRaw = $validated['fecha_ingreso'];
        $fechaIngreso = $fechaRaw !== null
            ? new DateTimeImmutable($fechaRaw . ' 00:00:00', $timezone)
            : null;

        if ($fechaIngreso !== null) {
            $result['dias_restantes'] = (int) $today->diff($fechaIngreso)->days;
        }

        $moneda = $validated['moneda'];
        $montoPrincipal = $validated['monto_centavos'];
        $hasErrors = $result['errors'] !== [];

        if (!$hasErrors && $moneda !== $principal) {
            try {
                $montoPrincipal = $this->currency()
                    ->toPrincipal($user, $validated['monto_centavos'], $moneda, $today->format('Y-m-d'))['centavos'];
            } catch (Throwable) {
                $result['errors'][] = 'planner.errors.currency_rate';
                $result['monto_principal_centavos'] = $validated['monto_centavos'];

                return $result;
            }
        }

        $result['monto_principal_centavos'] = $montoPrincipal;

        $sectionNames = array_values(array_map(
            static fn (array $category): string => (string) $category['nombre'],
            $categories
        ));

        if ($hasErrors || $montoPrincipal <= 0) {
            $reparto = array_fill_keys($sectionNames, 0);
        } else {
            try {
                $reparto = $this->reparto()->repartirIngreso($montoPrincipal, $percentages);
            } catch (InvalidArgumentException) {
                $result['errors'][] = 'planner.errors.percentages';

                return $result;
            }
        }

        $normalizedItems = $this->normalizeItems($items ?? [], $sectionNames, $warnings);
        $commitments = [];

        if (!$hasErrors && $fechaIngreso !== null) {
            $windowStart = $today;
            $windowEnd = $fechaIngreso->setTime(23, 59, 59);
            $commitments = $this->commitmentsForWindow($user, $windowStart, $windowEnd, $timezone, $now, $warnings);
        }

        $sections = $this->buildSections($categories, $percentages, $reparto, $commitments, $normalizedItems);

        $suggested = [];

        if ($items === null) {
            $suggested = $this->suggestedSavingsItems($user, $sectionNames);

            foreach ($suggested as $suggestion) {
                $normalizedItems[] = $suggestion;
            }

            if ($suggested !== []) {
                $sections = $this->buildSections($categories, $percentages, $reparto, $commitments, $normalizedItems);
            }
        }

        $summary = $this->summarize($sections, $commitments, $montoPrincipal);

        if (!$hasErrors && $summary['deficit_centavos'] > 0) {
            $warnings[] = [
                'key' => 'planner.warnings.deficit',
                'params' => [':amount' => format_money($summary['deficit_centavos'], $principal)],
            ];
        }

        if (!$hasErrors) {
            foreach ($sections as $section) {
                if (!empty($section['excedido']) && $section['tipo'] === 'controlada') {
                    $warnings[] = [
                        'key' => 'planner.warnings.section_over',
                        'params' => [
                            ':section' => $this->sectionLabel($section),
                            ':amount' => format_money(abs($section['disponible_centavos']), $principal),
                        ],
                    ];
                }

                if (!empty($section['items_excedido'])) {
                    $warnings[] = [
                        'key' => 'planner.warnings.items_over',
                        'params' => [
                            ':section' => $this->sectionLabel($section),
                            ':amount' => format_money(abs($section['items_disponible_centavos']), $principal),
                        ],
                    ];
                }
            }
        }

        $result['warnings'] = $warnings;
        $result['sections'] = $sections;
        $result['commitments'] = $commitments;
        $result['items'] = $normalizedItems;
        $result['suggested_items'] = $suggested;
        $result['summary'] = $summary;
        $result['debts'] = $this->debtContext($user);

        return $result;
    }

    /**
     * Agrupa compromisos e items en las secciones del método.
     *
     * @param list<array{nombre: string, porcentaje: ?int, tipo: string, orden: int}> $categories
     * @param array<string, int|float> $percentages
     * @param array<string, int> $reparto
     * @param list<array<string, mixed>> $commitments
     * @param list<array{seccion: string, concepto: string, monto_centavos: int}> $items
     * @return list<array<string, mixed>>
     */
    public function buildSections(
        array $categories,
        array $percentages,
        array $reparto,
        array $commitments,
        array $items = []
    ): array {
        $sections = [];
        $known = [];

        foreach ($categories as $category) {
            $name = (string) $category['nombre'];
            $known[$name] = true;
            $porcentaje = (float) ($percentages[$name] ?? $category['porcentaje'] ?? 0);
            $asignado = (int) ($reparto[$name] ?? 0);

            $sections[$name] = [
                'nombre' => $name,
                'tipo' => (string) $category['tipo'],
                'icon' => $this->iconFor((string) $category['tipo']),
                'porcentaje' => $porcentaje,
                'asignado_centavos' => $asignado,
                'comprometido_centavos' => 0,
                'commitments' => [],
                'items' => [],
                'items_centavos' => 0,
                'disponible_centavos' => $asignado,
                'items_disponible_centavos' => $asignado,
                'excedido' => false,
                'items_excedido' => false,
            ];
        }

        $orphan = [
            'nombre' => self::SECTION_SIN_SECCION,
            'tipo' => 'sin_seccion',
            'icon' => 'bi-question-circle',
            'porcentaje' => 0.0,
            'asignado_centavos' => 0,
            'comprometido_centavos' => 0,
            'commitments' => [],
            'items' => [],
            'items_centavos' => 0,
            'disponible_centavos' => 0,
            'items_disponible_centavos' => 0,
            'excedido' => false,
            'items_excedido' => false,
        ];

        foreach ($commitments as $commitment) {
            $section = (string) ($commitment['seccion'] ?? '');

            if ($section === '' || !isset($sections[$section])) {
                $orphan['comprometido_centavos'] += (int) $commitment['monto_centavos'];
                $orphan['commitments'][] = $commitment;
                continue;
            }

            $amount = (int) $commitment['monto_centavos'];
            $sections[$section]['comprometido_centavos'] += $amount;
            $sections[$section]['commitments'][] = $commitment;
        }

        foreach ($items as $item) {
            $section = (string) $item['seccion'];

            if ($section === '' || !isset($sections[$section])) {
                $orphan['items'][] = $item;
                $orphan['items_centavos'] += (int) $item['monto_centavos'];
                continue;
            }

            $sections[$section]['items'][] = $item;
            $sections[$section]['items_centavos'] += (int) $item['monto_centavos'];
        }

        foreach ($sections as &$section) {
            $section['disponible_centavos'] = $section['asignado_centavos'] - $section['comprometido_centavos'];
            $section['excedido'] = $section['disponible_centavos'] < 0;
            $section['items_disponible_centavos'] = $section['disponible_centavos'] - $section['items_centavos'];
            $section['items_excedido'] = $section['items_disponible_centavos'] < 0;
        }
        unset($section);

        if ($orphan['commitments'] !== [] || $orphan['items'] !== []) {
            $orphan['disponible_centavos'] = -$orphan['comprometido_centavos'];
            $orphan['excedido'] = $orphan['comprometido_centavos'] > 0;
            $orphan['items_disponible_centavos'] = -$orphan['comprometido_centavos'] - $orphan['items_centavos'];
            $orphan['items_excedido'] = $orphan['items_disponible_centavos'] < 0;
            $sections[self::SECTION_SIN_SECCION] = $orphan;
        }

        return array_values($sections);
    }

    /**
     * @param list<array<string, mixed>> $sections
     * @param list<array<string, mixed>> $commitments
     * @return array<string, int>
     */
    public function summarize(array $sections, array $commitments, int $incomeCents): array
    {
        $obligations = 0;

        foreach ($commitments as $commitment) {
            $obligations += (int) $commitment['monto_centavos'];
        }

        $reserved = 0;
        $spendAssigned = 0;
        $spendCommitted = 0;
        $orphanObligations = 0;

        foreach ($sections as $section) {
            $type = (string) $section['tipo'];
            $assigned = (int) $section['asignado_centavos'];
            $committed = (int) $section['comprometido_centavos'];

            if ($type === 'controlada') {
                $spendAssigned += $assigned;
                $spendCommitted += $committed;
            } elseif ($type === 'sin_seccion') {
                $orphanObligations += $committed;
            } else {
                $reserved += $assigned;
            }
        }

        $availableToSpend = $spendAssigned - $spendCommitted - $orphanObligations;
        $afterObligations = $incomeCents - $obligations;

        return [
            'ingreso_centavos' => $incomeCents,
            'obligaciones_centavos' => $obligations,
            'apartados_centavos' => $reserved,
            'gasto_asignado_centavos' => $spendAssigned,
            'gasto_comprometido_centavos' => $spendCommitted,
            'sin_seccion_centavos' => $orphanObligations,
            'disponible_gastar_centavos' => $availableToSpend,
            'despues_obligaciones_centavos' => $afterObligations,
            'sobrante_centavos' => max(0, $afterObligations - $reserved),
            'deficit_centavos' => max(0, $obligations - $incomeCents),
        ];
    }

    /**
     * Compromisos con fecha dentro de la ventana (incluye vencidos).
     *
     * @param array<string, mixed> $user
     * @param list<array{key: string, params?: array<string, string>}> $warnings
     * @return list<array<string, mixed>>
     */
    public function commitmentsForWindow(
        array $user,
        DateTimeImmutable $from,
        DateTimeImmutable $until,
        DateTimeZone $timezone,
        ?DateTimeImmutable $now = null,
        array &$warnings = []
    ): array {
        $commitments = [];
        $recurringKeys = [];
        $reminderDebts = [];
        $today = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone($timezone)
            ->setTime(0, 0, 0);

        foreach ($this->recurring()->allForUser((int) $user['id'], true) as $row) {
            $occurrences = $this->occurrences($row, $from, $until, $timezone, $warnings);

            foreach ($occurrences as $date) {
                $fecha = $date->format('Y-m-d');
                $key = 'r' . (int) $row['id'] . ':' . $fecha;
                $recurringKeys[$key] = true;
                $debtId = (int) ($row['deuda_id'] ?? 0);

                if ($debtId > 0) {
                    $reminderDebts[$debtId] = true;
                }

                $noControladoUntil = !empty($row['no_controlado_hasta'])
                    ? new DateTimeImmutable((string) $row['no_controlado_hasta'], new DateTimeZone('UTC'))
                    : null;
                $noControlado = $noControladoUntil !== null && $date <= $noControladoUntil;
                $section = $noControlado ? '' : $this->matchSection((string) ($row['parte_metodo'] ?? ''), $user);

                $commitments[] = [
                    'nombre' => (string) $row['nombre'],
                    'fecha' => $fecha,
                    'monto_centavos' => $this->amountInPrincipal($row, $user, $fecha),
                    'tipo' => $debtId > 0 ? 'deuda' : 'recurrente',
                    'seccion' => $section,
                    'seccion_original' => (string) ($row['parte_metodo'] ?? ''),
                    'es_suscripcion' => (int) ($row['es_suscripcion'] ?? 0) === 1,
                    'monto_variable' => (int) ($row['monto_variable'] ?? 0) === 1,
                    'no_controlado' => $noControlado,
                    'deuda_id' => $debtId > 0 ? $debtId : null,
                    'vencido' => $date < $today,
                    'hoy' => $date->format('Y-m-d') === $today->format('Y-m-d'),
                    'nota' => $row['notas'] ?? null,
                ];
            }
        }

        $defaultDebtSection = $this->debtSection($user);

        foreach ($this->debts()->allForUser((int) $user['id'], true) as $debt) {
            $debtId = (int) $debt['id'];
            $balance = (int) ($debt['saldo_actual_principal_centavos'] ?? $debt['saldo_actual_centavos'] ?? 0);

            if ($balance <= 0 || isset($reminderDebts[$debtId])) {
                continue;
            }

            $minimum = (int) ($debt['pago_minimo_principal_centavos'] ?? 0);

            if ($minimum <= 0) {
                $minimum = (int) ($debt['pago_minimo_centavos'] ?? 0);
            }

            $limit = $debt['fecha_limite'] ?? null;

            if ($minimum <= 0 || $limit === null || $limit === '') {
                continue;
            }

            $limitDate = new DateTimeImmutable((string) $limit . ' 00:00:00', $timezone);

            if ($limitDate > $until) {
                continue;
            }

            $commitments[] = [
                'nombre' => (string) $debt['nombre'],
                'fecha' => $limitDate->format('Y-m-d'),
                'monto_centavos' => $minimum,
                'tipo' => 'deuda',
                'seccion' => $defaultDebtSection ?? '',
                'seccion_original' => $defaultDebtSection ?? '',
                'es_suscripcion' => false,
                'monto_variable' => false,
                'no_controlado' => false,
                'deuda_id' => $debtId,
                'vencido' => $limitDate < $today,
                'hoy' => $limitDate->format('Y-m-d') === $today->format('Y-m-d'),
                'nota' => null,
            ];
        }

        foreach ($this->gastos()->pendingForUser((int) $user['id']) as $gasto) {
            $dateUtc = new DateTimeImmutable((string) $gasto['fecha'], new DateTimeZone('UTC'));
            $local = $dateUtc->setTimezone($timezone);
            $fecha = $local->format('Y-m-d');
            $recurringId = (int) ($gasto['gasto_recurrente_id'] ?? 0);

            if ($recurringId > 0 && isset($recurringKeys['r' . $recurringId . ':' . $fecha])) {
                continue;
            }

            $amount = $this->amountInPrincipal($gasto, $user, $fecha);

            if ($amount <= 0) {
                continue;
            }

            $part = (string) ($gasto['parte_metodo'] ?? '');
            $section = $this->matchSection($part, $user);

            $commitments[] = [
                'nombre' => (string) ($gasto['subcategoria_nombre'] ?: __('planner.commitments.pending_name')),
                'fecha' => $fecha,
                'monto_centavos' => $amount,
                'tipo' => 'pendiente',
                'seccion' => $section,
                'seccion_original' => $part,
                'es_suscripcion' => false,
                'monto_variable' => false,
                'no_controlado' => false,
                'deuda_id' => null,
                'vencido' => $local < $today,
                'hoy' => $fecha === $today->format('Y-m-d'),
                'nota' => $gasto['nota'] ?? null,
            ];
        }

        usort($commitments, static function (array $a, array $b): int {
            return [$a['fecha'], $a['nombre']] <=> [$b['fecha'], $b['nombre']];
        });

        return $commitments;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array{key: string, params?: array<string, string>}> $warnings
     * @return list<DateTimeImmutable>
     */
    private function occurrences(
        array $row,
        DateTimeImmutable $from,
        DateTimeImmutable $until,
        DateTimeZone $timezone,
        array &$warnings
    ): array {
        $cursor = (new DateTimeImmutable((string) $row['proxima_ejecucion'], new DateTimeZone('UTC')))->setTimezone($timezone);
        $recurrence = (string) ($row['recurrencia'] ?? 'mensual');
        $dates = [];
        $guard = 0;

        if ($cursor < $from) {
            $dates[] = $cursor;
        }

        while ($cursor < $from && $guard < 60) {
            $cursor = $this->recurrence()->next($cursor, $recurrence, $timezone);
            $guard++;
        }

        if ($cursor < $from) {
            $warnings[] = [
                'key' => 'planner.warnings.occurrences_skipped',
                'params' => [':name' => (string) $row['nombre']],
            ];

            return $dates;
        }

        while ($cursor <= $until && count($dates) < 60) {
            $dates[] = $cursor;
            $cursor = $this->recurrence()->next($cursor, $recurrence, $timezone);
        }

        if ($cursor <= $until) {
            $warnings[] = [
                'key' => 'planner.warnings.too_many_occurrences',
                'params' => [':name' => (string) $row['nombre']],
            ];
        }

        return $dates;
    }

    /**
     * @param list<array<string, mixed>> $raw
     * @param list<string> $sectionNames
     * @param list<array{key: string, params?: array<string, string>}> $warnings
     * @return list<array{seccion: string, concepto: string, monto_centavos: int}>
     */
    public function normalizeItems(array $raw, array $sectionNames, array &$warnings = []): array
    {
        $items = [];

        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $concept = trim((string) ($row['concepto'] ?? ''));
            $amountRaw = trim((string) ($row['monto'] ?? $row['monto_centavos'] ?? ''));

            if ($concept === '' && $amountRaw === '') {
                continue;
            }

            $amount = is_int($row['monto_centavos'] ?? null)
                ? (int) $row['monto_centavos']
                : parse_money_cents($amountRaw);

            if ($concept === '' || $amount === null || $amount <= 0) {
                $warnings[] = ['key' => 'planner.warnings.item_invalid'];
                continue;
            }

            $section = trim((string) ($row['seccion'] ?? ''));

            if (!in_array($section, $sectionNames, true)) {
                $warnings[] = ['key' => 'planner.warnings.item_orphan'];
                $section = self::SECTION_SIN_SECCION;
            }

            $items[] = [
                'seccion' => $section,
                'concepto' => mb_substr($concept, 0, 120),
                'monto_centavos' => $amount,
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $user
     * @param list<string> $sectionNames
     * @return list<array{seccion: string, concepto: string, monto_centavos: int}>
     */
    private function suggestedSavingsItems(array $user, array $sectionNames): array
    {
        $savingsSection = null;

        foreach ($sectionNames as $name) {
            if (mb_strtolower($name) === 'ahorro') {
                $savingsSection = $name;
                break;
            }
        }

        if ($savingsSection === null) {
            return [];
        }

        $items = [];

        foreach ($this->savings()->suggestedContributions($user) as $row) {
            $items[] = [
                'seccion' => $savingsSection,
                'concepto' => (string) $row['nombre'],
                'monto_centavos' => (int) $row['sugerido_centavos'],
            ];
        }

        return $items;
    }

    /**
     * Deudas activas como contexto (no se descuentan completas).
     *
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    private function debtContext(array $user): array
    {
        $rows = [];

        foreach ($this->debts()->allForUser((int) $user['id'], true) as $debt) {
            $balance = (int) ($debt['saldo_actual_principal_centavos'] ?? $debt['saldo_actual_centavos'] ?? 0);

            if ($balance <= 0) {
                continue;
            }

            $rows[] = [
                'id' => (int) $debt['id'],
                'nombre' => (string) $debt['nombre'],
                'saldo_centavos' => $balance,
                'fecha_limite' => $debt['fecha_limite'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $user
     */
    private function amountInPrincipal(array $row, array $user, string $date): int
    {
        $principal = (string) ($row['monto_principal_centavos'] ?? '');

        if ($principal !== '' && (int) $principal > 0) {
            return (int) $principal;
        }

        $amount = (int) ($row['monto_centavos'] ?? 0);
        $currency = strtoupper((string) ($row['moneda'] ?? $this->currency()->principal($user)));

        if ($currency === $this->currency()->principal($user)) {
            return $amount;
        }

        try {
            return $this->currency()->toPrincipal($user, $amount, $currency, $date)['centavos'];
        } catch (RuntimeException) {
            return $amount;
        }
    }

    /**
     * @param array<string, mixed> $user
     */
    private function matchSection(string $part, array $user): string
    {
        $method = $this->activeMethod($user);

        if ($method === null || $part === '') {
            return '';
        }

        foreach ($method['method']['categorias'] as $category) {
            if ((string) $category['nombre'] === $part) {
                return $part;
            }
        }

        foreach ($method['method']['categorias'] as $category) {
            if (mb_strtolower((string) $category['nombre']) === mb_strtolower($part)) {
                return (string) $category['nombre'];
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $user
     */
    private function debtSection(array $user): ?string
    {
        try {
            return $this->debtService()->defaultPaymentCategory($user);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $section
     */
    private function sectionLabel(array $section): string
    {
        $name = (string) $section['nombre'];

        if ($name === self::SECTION_SIN_SECCION) {
            return __('planner.sections.sin_seccion');
        }

        return $name;
    }

    private function iconFor(string $type): string
    {
        return match ($type) {
            'ahorro' => 'bi-piggy-bank',
            'inversion' => 'bi-graph-up-arrow',
            'donacion' => 'bi-heart',
            'compartir' => 'bi-people',
            'no_controlado' => 'bi-question-circle',
            default => 'bi-cart',
        };
    }

    /**
     * @return array<string, int>
     */
    private function emptySummary(): array
    {
        return [
            'ingreso_centavos' => 0,
            'obligaciones_centavos' => 0,
            'apartados_centavos' => 0,
            'gasto_asignado_centavos' => 0,
            'gasto_comprometido_centavos' => 0,
            'sin_seccion_centavos' => 0,
            'disponible_gastar_centavos' => 0,
            'despues_obligaciones_centavos' => 0,
            'sobrante_centavos' => 0,
            'deficit_centavos' => 0,
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    private function principalOf(array $user): string
    {
        $code = strtoupper((string) ($user['moneda_activa'] ?? 'MXN'));

        return in_array($code, CurrencyService::SUPPORTED, true) ? $code : 'MXN';
    }

    private function methods(): MethodService
    {
        return $this->methods ??= new MethodService();
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    private function activeMethod(array $user): ?array
    {
        if (!$this->methodLoaded) {
            $this->methodCache = $this->methods()->activeData($user);
            $this->methodLoaded = true;
        }

        return $this->methodCache;
    }

    private function reparto(): RepartoService
    {
        return $this->reparto ??= new RepartoService();
    }

    private function recurring(): RecurringExpenseRepository
    {
        return $this->recurring ??= new RecurringExpenseRepository();
    }

    private function debts(): DebtRepository
    {
        return $this->debts ??= new DebtRepository();
    }

    private function gastos(): GastoRepository
    {
        return $this->gastos ??= new GastoRepository();
    }

    private function savings(): SavingsService
    {
        return $this->savings ??= new SavingsService();
    }

    private function currency(): CurrencyService
    {
        return $this->currency ??= new CurrencyService();
    }

    private function periods(): PeriodoService
    {
        return $this->periods ??= new PeriodoService();
    }

    private function recurrence(): RecurrenceService
    {
        return $this->recurrence ??= new RecurrenceService();
    }

    private function debtService(): DebtService
    {
        return $this->debtService ??= new DebtService();
    }
}
