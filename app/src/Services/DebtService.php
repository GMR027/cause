<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Repositories\DebtRepository;
use Cauce\Repositories\RecurringExpenseRepository;
use Cauce\Repositories\UserRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Deudas, pagos, estrategia y recordatorios.
 */
final class DebtService
{
    public const STRATEGIES = ['bola_nieve', 'avalancha', 'manual'];

    public const ORIGINS = ['ingreso', 'ahorro', 'tarjeta', 'otro'];

    /** De dónde vino el dinero de la deuda al crearla. */
    public const DEBT_ORIGINS = ['tarjeta', 'ahorro', 'prestamo', 'otro'];

    private DebtRepository $repo;

    private PDO $db;

    public function __construct(?DebtRepository $repo = null, ?PDO $db = null)
    {
        $this->repo = $repo ?? new DebtRepository();
        $this->db = $db ?? Database::connection();
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function panel(array $user): array
    {
        $userId = (int) $user['id'];
        $strategy = $this->strategyOf($user);
        $debts = $this->repo->allForUser($userId);
        $priorityId = $this->priorityId($debts, $strategy);
        $today = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
        $totalOriginal = 0;
        $totalBalance = 0;

        foreach ($debts as &$debt) {
            $original = max(0, (int) $debt['monto_total_centavos']);
            $balance = max(0, (int) $debt['saldo_actual_centavos']);
            $originalPrincipal = max(0, (int) ($debt['monto_total_principal_centavos'] ?? $original));
            $balancePrincipal = max(0, (int) ($debt['saldo_actual_principal_centavos'] ?? $balance));
            $paid = max(0, $original - $balance);
            $reminder = $this->repo->reminderForDebt((int) $debt['id'], $userId);
            $limit = $debt['fecha_limite'] !== null ? (string) $debt['fecha_limite'] : null;
            $reminderDate = $reminder !== null ? (string) $reminder['proxima_ejecucion'] : null;
            $next = $this->earliest($limit, $reminderDate);

            $debt['original_centavos'] = $original;
            $debt['saldo_centavos'] = $balance;
            $debt['pagado_centavos'] = $paid;
            $debt['progreso'] = $original > 0 ? min(100, (int) round(($paid / $original) * 100)) : 0;
            $debt['prioritaria'] = $priorityId !== null && (int) $debt['id'] === $priorityId;
            $debt['proximo_pago'] = $next;
            $debt['vencida'] = $limit !== null && $limit < $today && (int) $debt['activa'] === 1;
            $debt['recordatorio'] = $reminder;

            if ((int) $debt['activa'] === 1) {
                $totalBalance += $balancePrincipal;
            }

            $totalOriginal += $originalPrincipal;
        }
        unset($debt);

        $categories = $this->paymentCategories($user);
        $defaultCategory = $this->defaultCategory($categories);

        return [
            'debts' => $debts,
            'payments' => $this->repo->paymentsForUser($userId, 30),
            'strategy' => $strategy,
            'strategies' => [
                'bola_nieve' => __('debts.strategy.bola_nieve'),
                'avalancha' => __('debts.strategy.avalancha'),
                'manual' => __('debts.strategy.manual'),
            ],
            'total_original_centavos' => $totalOriginal,
            'total_saldo_centavos' => $totalBalance,
            'total_pagado_centavos' => max(0, $totalOriginal - $totalBalance),
            'total_progreso' => $totalOriginal > 0
                ? min(100, (int) round((max(0, $totalOriginal - $totalBalance) / $totalOriginal) * 100))
                : 0,
            'categories' => $categories,
            'default_category' => $defaultCategory,
            'savings_bags' => (new SavingsService())->debtSources($user),
            'currency' => (string) ($user['moneda_activa'] ?? 'MXN'),
            'active_count' => $this->repo->countActive($userId),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     */
    public function create(array $user, array $data): int
    {
        $normalized = $this->normalize($user, $data);
        $origin = in_array($data['origen'] ?? 'otro', self::DEBT_ORIGINS, true) ? (string) $data['origen'] : 'otro';
        $accountId = (int) ($data['origen_cuenta_id'] ?? 0) ?: null;
        $metaId = (int) ($data['origen_meta_id'] ?? 0) ?: null;

        if ($origin !== 'ahorro') {
            return $this->repo->create($normalized + ['origen' => $origin]);
        }

        // Préstamo de una bolsa propia: el saldo que se debe sale de esa bolsa.
        $savings = new SavingsService();
        $bag = $this->savingsBag($savings->debtSources($user), $accountId, $metaId);

        if ($bag === null) {
            throw new InvalidArgumentException('sin_bolsa');
        }

        if ((string) $bag['moneda'] !== (string) $normalized['moneda']) {
            throw new InvalidArgumentException('moneda_bolsa');
        }

        $this->db->beginTransaction();

        try {
            $id = $this->repo->create($normalized + [
                'origen' => 'ahorro',
                'origen_cuenta_id' => (int) $bag['id'],
                'origen_meta_id' => $metaId,
            ]);

            if ((int) $normalized['saldo_actual_centavos'] > 0) {
                $withdrawal = $savings->withdrawEntry(
                    $user,
                    (int) $bag['id'],
                    $metaId,
                    (int) $normalized['saldo_actual_centavos'],
                    'otro',
                    __('debts.origin.loan_note', [':name' => (string) $normalized['nombre']]),
                    'now'
                );
                $this->repo->setOriginWithdrawal($id, (int) $user['id'], (int) $withdrawal['id']);
            }

            $this->db->commit();

            return $id;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     */
    public function update(array $user, int $id, array $data): void
    {
        $debt = $this->requireDebt($user, $id);
        $normalized = $this->normalize($user, $data, $debt);
        $this->repo->update($id, (int) $user['id'], $normalized);

        // Editar el saldo a cero también es liquidarla: se festeja igual.
        if ((int) $debt['activa'] === 1 && !$normalized['activa']) {
            DebtPaidFxService::queue($user, array_merge($debt, [
                'nombre' => $normalized['nombre'],
                'monto_total_centavos' => $normalized['monto_total_centavos'],
                'moneda' => $normalized['moneda'],
            ]));
        }
    }

    /**
     * @param array<string, mixed> $user
     */
    public function delete(array $user, int $id): void
    {
        $debt = $this->requireDebt($user, $id);
        $userId = (int) $user['id'];
        (new CoverService())->forget($userId, 'deuda', $id);
        $this->db->beginTransaction();

        try {
            $this->db->prepare(
                'DELETE FROM gastos_recurrentes WHERE deuda_id = ? AND user_id = ?'
            )->execute([$id, $userId]);

            // Préstamo de una bolsa: la bolsa vuelve a como estaba antes de la deuda
            // (se borran el retiro original y lo que los pagos le devolvieron).
            if (!empty($debt['retiro_ahorro_id'])) {
                foreach ($this->repo->returnContributionIds($id, $userId) as $contributionId) {
                    $this->db->prepare('DELETE FROM aportaciones_ahorro WHERE id = ? AND user_id = ?')
                        ->execute([$contributionId, $userId]);
                }

                $this->db->prepare('DELETE FROM retiros_ahorro WHERE id = ? AND user_id = ?')
                    ->execute([(int) $debt['retiro_ahorro_id'], $userId]);
            }

            $this->repo->delete($id, $userId);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $user
     */
    public function setStrategy(array $user, string $strategy): void
    {
        if (!in_array($strategy, self::STRATEGIES, true)) {
            throw new InvalidArgumentException('Estrategia de deuda inválida.');
        }

        (new UserRepository())->updateDebtStrategy((int) $user['id'], $strategy);
    }

    /**
     * Registra un pago y descuenta el dinero de donde salió:
     * · ingreso: gasto en una categoría del método (período actual).
     * · ahorro: retiro de una bolsa (o de una de sus metas).
     * · tarjeta: el monto se suma a otra deuda (la tarjeta de crédito).
     * · otro: solo baja el saldo de la deuda.
     *
     * @param array<string, mixed> $user
     * @param array{cuenta_id?: int|null, meta_id?: int|null, tarjeta_id?: int|null} $source
     * @return array<string, mixed>
     */
    public function pay(
        array $user,
        int $debtId,
        int $amount,
        string $date,
        ?string $note,
        string $origin,
        ?string $category,
        array $source = []
    ): array {
        $debt = $this->requireDebt($user, $debtId);
        $currency = (string) $debt['moneda'];

        if (!in_array($origin, self::ORIGINS, true)) {
            throw new InvalidArgumentException('origen_invalido');
        }

        if ((int) $debt['activa'] !== 1) {
            throw new RuntimeException('La deuda ya está liquidada.');
        }

        $balance = (int) $debt['saldo_actual_centavos'];

        if ($amount <= 0) {
            throw new InvalidArgumentException('El pago debe ser mayor que cero.');
        }

        if ($amount > $balance) {
            throw new InvalidArgumentException('El pago no puede superar el saldo actual.');
        }

        $timezone = (new PeriodoService())->userTimezone($user);
        $utc = $this->toUtc($date, $timezone);
        $cleanNote = $note !== null && trim($note) !== '' ? trim($note) : null;
        $card = null;

        if ($origin === 'ingreso') {
            $period = (new FinanceSupportService())->period($user);

            if ($utc < $period['inicio_dt'] || $utc > $period['fin_dt']) {
                throw new RuntimeException('fuera_periodo');
            }

            if ($category === null || trim($category) === '') {
                throw new InvalidArgumentException('Elige una categoría para el gasto.');
            }

            $allowed = array_column($this->paymentCategories($user), 'nombre');

            if (!in_array($category, $allowed, true)) {
                throw new InvalidArgumentException('La categoría del gasto no es válida.');
            }
        }

        if ($origin === 'ahorro' && (int) ($source['cuenta_id'] ?? 0) <= 0 && (int) ($source['meta_id'] ?? 0) <= 0) {
            throw new InvalidArgumentException('sin_bolsa');
        }

        if ($origin === 'tarjeta') {
            $cardId = (int) ($source['tarjeta_id'] ?? 0);

            if ($cardId > 0) {
                $card = $this->requireDebt($user, $cardId);

                if ($cardId === $debtId) {
                    throw new InvalidArgumentException('misma_tarjeta');
                }

                if ((string) $card['moneda'] !== $currency) {
                    throw new InvalidArgumentException('moneda_tarjeta');
                }
            }
        }

        $gastoId = null;
        $withdrawalId = null;
        $expense = null;
        $defaultNote = __('debts.payment.expense_note', [':name' => (string) $debt['nombre']]);
        $this->db->beginTransaction();

        try {
            if ($origin === 'ingreso') {
                $expense = (new GastoService())->crearManual($user, [
                    'monto_centavos' => $amount,
                    'parte_metodo' => (string) $category,
                    'fecha' => $date,
                    'moneda' => $currency,
                    'nota' => $cleanNote !== null ? mb_substr($cleanNote, 0, 2000) : $defaultNote,
                    'fuente_exceso' => 'no_aplica',
                    'tags' => [],
                ]);
                $gastoId = (int) ($expense['gasto_id'] ?? 0) ?: null;
            }

            if ($origin === 'ahorro') {
                $savings = new SavingsService();
                $metaId = (int) ($source['meta_id'] ?? 0) ?: null;
                $accountId = (int) ($source['cuenta_id'] ?? 0) ?: null;
                $bag = $this->savingsBag($savings->debtSources($user), $accountId, $metaId);

                if ($bag === null) {
                    throw new RuntimeException('savings.errors.account_not_found');
                }

                if ((string) $bag['moneda'] !== $currency) {
                    throw new InvalidArgumentException('moneda_bolsa');
                }

                $withdrawalId = $savings->withdrawEntry(
                    $user,
                    (int) $bag['id'],
                    $metaId,
                    $amount,
                    'otro',
                    $defaultNote,
                    $date
                )['id'];
            }

            if ($card !== null) {
                $principalCharge = (new CurrencyService())->toPrincipal($user, $amount, $currency, $utc->format('Y-m-d'))['centavos'];
                $this->repo->increaseBalance((int) $card['id'], (int) $user['id'], $amount, $principalCharge);
            }

            $contributionId = $this->returnToBag($user, $debt, $amount, $utc);

            $paymentId = $this->repo->createPayment([
                'deuda_id' => $debtId,
                'user_id' => (int) $user['id'],
                'monto_centavos' => $amount,
                'moneda' => $currency,
                'fecha' => $utc->format('Y-m-d H:i:s'),
                'gasto_id' => $gastoId,
                'origen' => $origin,
                'retiro_ahorro_id' => $withdrawalId,
                'deuda_tarjeta_id' => $card !== null ? (int) $card['id'] : null,
                'aporte_ahorro_id' => $contributionId,
                'nota' => $cleanNote !== null ? mb_substr($cleanNote, 0, 255) : null,
            ]);

            $principalPayment = (new CurrencyService())->toPrincipal($user, $amount, (string) $debt['moneda'], $utc->format('Y-m-d'))['centavos'];
            $this->repo->reduceBalance($debtId, (int) $user['id'], $amount, $principalPayment);
            $this->db->commit();
            $settled = $amount >= $balance;

            if ($settled) {
                DebtPaidFxService::queue($user, $debt);
            }

            return [
                'payment_id' => $paymentId,
                'gasto_id' => $gastoId,
                'retiro_id' => $withdrawalId,
                'expense' => $expense,
                'amount_centavos' => $amount,
                'debt' => $debt,
                'settled' => $settled,
            ];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Si la deuda fue un préstamo de una bolsa propia, el pago regresa a esa
     * bolsa (o meta). Cuenta como saldo ya ahorrado: no usa el ahorro del
     * período, porque el dinero ya salió del ingreso o de donde se pagó.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $debt
     */
    private function returnToBag(array $user, array $debt, int $amount, DateTimeImmutable $utc): ?int
    {
        if ((string) ($debt['origen'] ?? '') !== 'ahorro' || empty($debt['origen_cuenta_id'])) {
            return null;
        }

        $userId = (int) $user['id'];
        $savingsRepo = new \Cauce\Repositories\SavingsRepository();
        $account = $savingsRepo->findAccount((int) $debt['origen_cuenta_id'], $userId);

        if ($account === null || (int) $account['activa'] !== 1 || (string) $account['moneda'] !== (string) $debt['moneda']) {
            return null;
        }

        $metaId = null;

        if (!empty($debt['origen_meta_id'])) {
            $meta = $savingsRepo->findMeta((int) $debt['origen_meta_id'], $userId);

            if ($meta !== null && (int) ($meta['activa'] ?? 1) === 1) {
                $metaId = (int) $meta['id'];
            }
        }

        return $savingsRepo->createContribution(
            $userId,
            (int) $account['id'],
            $metaId,
            $amount,
            (string) $account['moneda'],
            $utc->format('Y-m-d H:i:s'),
            true
        );
    }

    /**
     * @param list<array<string, mixed>> $bags
     * @return array<string, mixed>|null
     */
    private function savingsBag(array $bags, ?int $accountId, ?int $metaId): ?array
    {
        foreach ($bags as $bag) {
            if ($metaId !== null) {
                foreach ($bag['metas'] as $meta) {
                    if ((int) $meta['id'] === $metaId) {
                        return $bag;
                    }
                }

                continue;
            }

            if ((int) $bag['id'] === $accountId) {
                return $bag;
            }
        }

        return null;
    }

    /**
     * Aplica el exceso de un gasto a una deuda existente o crea una nueva.
     *
     * Se llama dentro de la transacción del gasto.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     */
    public function applyExcessFromExpense(array $user, array $data, int $excess): ?int
    {
        if ($excess <= 0) {
            return null;
        }

        $debtId = (int) ($data['deuda_id'] ?? 0);

        if ($debtId > 0) {
            $debt = $this->repo->findForUser($debtId, (int) $user['id']);

            if ($debt === null) {
                throw new InvalidArgumentException('La deuda seleccionada no existe.');
            }

            $this->repo->applyExcess($debtId, (int) $user['id'], $excess);

            return $debtId;
        }

        $name = trim((string) ($data['nueva_deuda_nombre'] ?? ''));
        $currency = (new CurrencyService())->principal($user);

        if ($name === '') {
            throw new InvalidArgumentException('Escribe el nombre de la nueva deuda.');
        }

        return $this->repo->create([
            'user_id' => (int) $user['id'],
            'nombre' => mb_substr($name, 0, 120),
            'monto_total_centavos' => $excess,
            'saldo_actual_centavos' => $excess,
            'moneda' => $currency,
            'tasa_interes' => $this->rateOrNull($data['nueva_deuda_tasa'] ?? null),
            'pago_minimo_centavos' => $this->moneyOrNull($data['nueva_deuda_minimo'] ?? null),
            'fecha_limite' => $this->dateOrNull($data['nueva_deuda_fecha_limite'] ?? null),
        ]);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function createReminder(
        array $user,
        int $debtId,
        int $amount,
        string $recurrence,
        string $nextDate,
        string $category
    ): int {
        $debt = $this->requireDebt($user, $debtId);

        if ((int) ($debt['activa'] ?? 1) !== 1 || (int) ($debt['saldo_actual_centavos'] ?? 0) <= 0) {
            throw new RuntimeException('La deuda ya está liquidada.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('El recordatorio necesita un monto mayor que cero.');
        }

        if (!in_array($recurrence, ['diaria', 'semanal', 'mensual', 'anual'], true)) {
            throw new InvalidArgumentException('Recurrencia inválida.');
        }

        $allowed = array_column($this->paymentCategories($user), 'nombre');

        if (!in_array($category, $allowed, true)) {
            throw new InvalidArgumentException('La categoría del recordatorio no es válida.');
        }

        $timezone = (new PeriodoService())->userTimezone($user);
        $next = $this->toUtc($nextDate, $timezone);
        $today = (new DateTimeImmutable('now', $timezone))->setTime(0, 0, 0);

        if ($next->setTimezone($timezone) < $today) {
            throw new InvalidArgumentException('La próxima fecha no puede ser anterior a hoy.');
        }

        $this->db->prepare(
            'UPDATE gastos_recurrentes SET activo = 0, updated_at = NOW()
             WHERE deuda_id = ? AND user_id = ? AND activo = 1'
        )->execute([$debtId, (int) $user['id']]);

        return (new RecurringExpenseRepository($this->db))->create(
            (int) $user['id'],
            mb_substr((string) $debt['nombre'], 0, 120),
            $amount,
            (string) $debt['moneda'],
            false,
            $category,
            null,
            $recurrence,
            $next->format('Y-m-d H:i:s'),
            $debtId
        );
    }

    /**
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    public function paymentCategories(array $user): array
    {
        $categories = (new GastoService())->categoriasActivas($user);

        return array_values(array_filter($categories, static function (array $category): bool {
            $type = (string) ($category['tipo'] ?? '');
            $name = mb_strtolower((string) ($category['nombre'] ?? ''));

            return $type === 'controlada' || str_contains($name, 'deuda');
        }));
    }

    /**
     * Categoría del método a la que se asignan los pagos de deuda.
     *
     * @param array<string, mixed> $user
     */
    public function defaultPaymentCategory(array $user): ?string
    {
        return $this->defaultCategory($this->paymentCategories($user));
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function requireDebt(array $user, int $id): array
    {
        $debt = $this->repo->findForUser($id, (int) $user['id']);

        if ($debt === null) {
            throw new RuntimeException('La deuda no existe.');
        }

        return $debt;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function normalize(array $user, array $data, ?array $existing = null): array
    {
        $name = trim((string) ($data['nombre'] ?? ''));
        $currencyInput = trim((string) ($data['moneda'] ?? ''));
        $currency = (new CurrencyService())->validateCode(
            $currencyInput !== ''
                ? $currencyInput
                : (string) ($existing['moneda'] ?? $user['moneda_activa'] ?? 'MXN')
        );
        $total = (int) ($data['monto_total_centavos'] ?? 0);
        $balance = (int) ($data['saldo_actual_centavos'] ?? $total);
        $rate = $this->rateOrNull($data['tasa_interes'] ?? null);
        $minimum = $this->moneyOrNull($data['pago_minimo_centavos'] ?? null);
        $limit = $this->dateOrNull($data['fecha_limite'] ?? null);

        if ($name === '') {
            throw new InvalidArgumentException('La deuda necesita un nombre.');
        }

        if ($total <= 0) {
            throw new InvalidArgumentException('El monto total debe ser mayor que cero.');
        }

        if ($balance < 0 || $balance > $total) {
            throw new InvalidArgumentException('El saldo actual debe estar entre cero y el monto total.');
        }

        if ($rate !== null && ($rate < 0 || $rate > 100)) {
            throw new InvalidArgumentException('La tasa de interés debe estar entre 0 y 100.');
        }

        if ($minimum !== null && $minimum < 0) {
            throw new InvalidArgumentException('El pago mínimo no puede ser negativo.');
        }

        return [
            'user_id' => (int) $user['id'],
            'nombre' => mb_substr($name, 0, 120),
            'monto_total_centavos' => $total,
            'saldo_actual_centavos' => $balance,
            'moneda' => $currency,
            'tasa_interes' => $rate,
            'pago_minimo_centavos' => $minimum,
            'fecha_limite' => $limit,
            'activa' => $balance > 0,
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    private function strategyOf(array $user): string
    {
        $strategy = (string) ($user['estrategia_deuda'] ?? 'manual');

        return in_array($strategy, self::STRATEGIES, true) ? $strategy : 'manual';
    }

    /**
     * @param list<array<string, mixed>> $debts
     */
    private function priorityId(array $debts, string $strategy): ?int
    {
        $active = array_values(array_filter($debts, static fn (array $debt): bool => (int) $debt['activa'] === 1));

        if ($active === [] || $strategy === 'manual') {
            return null;
        }

        usort($active, static function (array $a, array $b) use ($strategy): int {
            if ($strategy === 'avalancha') {
                $rateA = (float) ($a['tasa_interes'] ?? 0);
                $rateB = (float) ($b['tasa_interes'] ?? 0);

                if ($rateA !== $rateB) {
                    return $rateB <=> $rateA;
                }
            }

            return (int) ($a['saldo_actual_principal_centavos'] ?? $a['saldo_actual_centavos'])
                    <=> (int) ($b['saldo_actual_principal_centavos'] ?? $b['saldo_actual_centavos']);
        });

        return (int) $active[0]['id'];
    }

    /**
     * @param list<array<string, mixed>> $categories
     */
    private function defaultCategory(array $categories): ?string
    {
        foreach ($categories as $category) {
            if (str_contains(mb_strtolower((string) $category['nombre']), 'deuda')) {
                return (string) $category['nombre'];
            }
        }

        return $categories !== [] ? (string) $categories[0]['nombre'] : null;
    }

    private function earliest(?string $dateA, ?string $dateB): ?string
    {
        $dates = array_values(array_filter([$dateA, $dateB], static fn (?string $date): bool => $date !== null && $date !== ''));

        if ($dates === []) {
            return null;
        }

        sort($dates);

        return $dates[0];
    }

    private function rateOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $rate = (float) str_replace(',', '.', (string) $value);

        return round($rate, 2);
    }

    private function moneyOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return max(0, $value);
        }

        $parsed = parse_money_cents((string) $value);

        return $parsed === null ? null : max(0, $parsed);
    }

    private function dateOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw new InvalidArgumentException('La fecha no es válida.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('La fecha no es válida.');
        }

        return $value;
    }

    private function toUtc(string $value, DateTimeZone $timezone): DateTimeImmutable
    {
        $value = trim($value);

        if ($value === '' || $value === 'now') {
            return new DateTimeImmutable('now', $timezone);
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) === 1) {
            if (!checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
                throw new InvalidArgumentException('La fecha no es válida.');
            }

            $value .= ' 12:00:00';
        }

        return (new DateTimeImmutable($value, $timezone))->setTimezone(new DateTimeZone('UTC'));
    }
}
