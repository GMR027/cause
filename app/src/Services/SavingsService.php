<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Repositories\DebtRepository;
use Cauce\Repositories\SavingsRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Panel de ahorro: bolsa única, metas y fondo de emergencia.
 */
final class SavingsService
{
    private const META_TYPES = ['ahorro', 'retiro', 'casa', 'educacion', 'otro', 'deseo'];
    private const WITHDRAW_REASONS = ['emergencia', 'meta_cumplida', 'reasignacion', 'otro'];

    private SavingsRepository $repo;

    public function __construct(?SavingsRepository $repo = null)
    {
        $this->repo = $repo ?? new SavingsRepository();
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function panel(array $user): array
    {
        $support = new FinanceSupportService();
        $period = $support->period($user);
        $principal = (string) ($user['moneda_activa'] ?? 'MXN');
        $this->repo->ensureDefaultAccount((int) $user['id'], $principal);
        $emergency = (new EmergencyFundService())->ensure($user);
        $metas = $this->repo->metasForUser((int) $user['id']);
        $totals = $this->repo->totalsByMeta((int) $user['id']);
        $emergencyId = (int) ($emergency['meta']['id'] ?? 0);
        $metaRows = [];

        foreach ($metas as $meta) {
            $id = (int) $meta['id'];
            $aportado = (int) ($totals[$id]['aportado'] ?? 0);
            $retirado = (int) ($totals[$id]['retirado'] ?? 0);
            $acumulado = $aportado - $retirado;
            $objetivo = (int) $meta['objetivo_centavos'];
            $metaCurrency = (string) ($meta['moneda'] ?? $principal);
            $today = gmdate('Y-m-d');
            $objetivoPrincipal = $objetivo;

            if ($metaCurrency !== $principal) {
                try {
                    $objetivoPrincipal = (new CurrencyService())->toPrincipal($user, $objetivo, $metaCurrency, $today)['centavos'];
                } catch (RuntimeException) {
                    $objetivoPrincipal = 0;
                }
            }

            $acumuladoMeta = $acumulado;

            if ($metaCurrency !== $principal) {
                try {
                    $acumuladoMeta = (new CurrencyService())->convert($user, $acumulado, $principal, $metaCurrency, $today)['centavos'];
                } catch (RuntimeException) {
                    $acumuladoMeta = 0;
                }
            }

            $metaRows[] = array_merge($meta, [
                'aportado_centavos' => $aportado,
                'retirado_centavos' => $retirado,
                'acumulado_centavos' => $acumuladoMeta,
                'acumulado_principal_centavos' => $acumulado,
                'objetivo_principal_centavos' => $objetivoPrincipal,
                'progreso' => $objetivoPrincipal > 0 ? min(100, (int) round(($acumulado / $objetivoPrincipal) * 100)) : 0,
                'sugerido_mensual_centavos' => $this->monthlySuggestion($meta, $acumuladoMeta),
                'plazo_restante_meses' => $this->remainingMonths($meta),
                'cumplida' => $objetivoPrincipal > 0 && $acumulado >= $objetivoPrincipal,
                'es_emergencia_actual' => $id === $emergencyId,
            ]);
        }

        $accounts = $this->repo->accountsForUser((int) $user['id']);

        foreach ($accounts as &$account) {
            $account['asignado_principal_centavos'] = 0;
            $account['metas_count'] = 0;
        }
        unset($account);

        foreach ($metaRows as $metaRow) {
            $accountId = (int) ($metaRow['cuenta_id'] ?? 0);

            foreach ($accounts as &$account) {
                if ((int) $account['id'] === $accountId) {
                    $account['asignado_principal_centavos'] += (int) ($metaRow['acumulado_principal_centavos'] ?? 0);
                    $account['metas_count']++;
                    break;
                }
            }
            unset($account);
        }

        foreach ($accounts as &$account) {
            $account['sin_asignar_principal_centavos'] = (int) $account['saldo_principal_centavos']
                - (int) $account['asignado_principal_centavos'];
        }
        unset($account);

        $defaultAccount = $this->repo->defaultAccount((int) $user['id']);
        $emergencySaved = 0;

        foreach ($metaRows as $metaRow) {
            if (!empty($metaRow['es_emergencia_actual'])) {
                $emergencySaved = (int) ($metaRow['acumulado_principal_centavos'] ?? 0);
                break;
            }
        }

        $periodContributions = $this->repo->sumPeriodContributions((int) $user['id'], $period['inicio'], $period['fin']);
        $assigned = $support->assignedForType($user, 'ahorro');
        $contributions = $this->repo->recentContributions((int) $user['id'], 15);
        $withdrawals = $this->repo->recentWithdrawals((int) $user['id'], 15);
        $transfers = $this->repo->recentTransfers((int) $user['id'], 15);

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
            'total_centavos' => array_sum(array_column($accounts, 'saldo_principal_centavos')),
            'bag_centavos' => array_sum(array_column($accounts, 'sin_asignar_principal_centavos')),
            'accounts' => $accounts,
            'default_account_id' => (int) ($defaultAccount['id'] ?? 0),
            'metas' => $metaRows,
            'emergency' => $emergency,
            'emergency_saved_centavos' => $emergencySaved,
            'assigned_centavos' => $assigned,
            'period_contributions_centavos' => $periodContributions,
            'available_centavos' => max(0, $assigned - $periodContributions),
            'over_percentage' => $periodContributions > $assigned,
            'contributions' => $contributions,
            'withdrawals' => $withdrawals,
            'transfers' => $transfers,
            'meta_types' => self::META_TYPES,
            'withdraw_reasons' => self::WITHDRAW_REASONS,
            'currency' => $principal,
        ];
    }

    /**
     * Aportes mensuales sugeridos para metas activas, en moneda principal.
     *
     * Es de solo lectura: no crea cuenta predeterminada ni fondo de emergencia.
     * Se usa en el planeador para precargar conceptos de la sección Ahorro.
     *
     * @param array<string, mixed> $user
     * @return list<array{meta_id: int, nombre: string, tipo: string, sugerido_centavos: int}>
     */
    public function suggestedContributions(array $user): array
    {
        $principal = (string) ($user['moneda_activa'] ?? 'MXN');
        $metas = $this->repo->metasForUser((int) $user['id']);
        $totals = $this->repo->totalsByMeta((int) $user['id']);
        $currency = new CurrencyService();
        $today = gmdate('Y-m-d');
        $rows = [];

        foreach ($metas as $meta) {
            if ((int) ($meta['activa'] ?? 1) !== 1) {
                continue;
            }

            if ((string) ($meta['tipo'] ?? '') === 'deseo') {
                continue;
            }

            $id = (int) $meta['id'];
            $metaCurrency = (string) ($meta['moneda'] ?? $principal);
            $objective = (int) $meta['objetivo_centavos'];
            $accumulated = (int) ($totals[$id]['aportado'] ?? 0) - (int) ($totals[$id]['retirado'] ?? 0);

            if ($metaCurrency !== $principal) {
                try {
                    $objective = $currency->toPrincipal($user, $objective, $metaCurrency, $today)['centavos'];
                } catch (RuntimeException) {
                    continue;
                }
            }

            $forSuggestion = $meta;
            $forSuggestion['objetivo_centavos'] = $objective;
            $suggestion = $this->monthlySuggestion($forSuggestion, $accumulated);

            if ($suggestion === null || $suggestion <= 0) {
                continue;
            }

            $rows[] = [
                'meta_id' => $id,
                'nombre' => (string) $meta['nombre'],
                'tipo' => (string) $meta['tipo'],
                'sugerido_centavos' => (int) $suggestion,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     */
    public function createMeta(array $user, array $data): int
    {
        $name = trim((string) ($data['nombre'] ?? ''));
        $objective = (int) ($data['objetivo_centavos'] ?? 0);
        $type = (string) ($data['tipo'] ?? 'ahorro');
        $targetDate = trim((string) ($data['fecha_objetivo'] ?? ''));
        $months = isset($data['plazo_meses']) && $data['plazo_meses'] !== '' ? (int) $data['plazo_meses'] : null;
        $accountId = (int) ($data['cuenta_id'] ?? 0);

        if ($name === '') {
            throw new InvalidArgumentException('La meta necesita un nombre.');
        }

        if ($objective <= 0) {
            throw new InvalidArgumentException('El objetivo debe ser mayor que cero.');
        }

        if (!in_array($type, self::META_TYPES, true)) {
            throw new InvalidArgumentException('Tipo de meta inválido.');
        }

        $account = $this->requireAccount($user, $accountId);
        $accountId = (int) $account['id'];
        $currency = (string) $account['moneda'];

        if ($targetDate !== '' && !$this->validDate($targetDate)) {
            throw new InvalidArgumentException('La fecha objetivo no es válida.');
        }

        return $this->repo->createMeta(
            (int) $user['id'],
            $accountId,
            mb_substr($name, 0, 120),
            $type,
            $objective,
            $currency,
            $targetDate !== '' ? $targetDate : null,
            $months !== null ? max(1, min(120, $months)) : null
        );
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     */
    public function updateMeta(array $user, int $metaId, array $data): void
    {
        $meta = $this->requireMeta($user, $metaId);

        if ((int) $meta['es_emergencia'] === 1) {
            throw new RuntimeException('El fondo de emergencia se actualiza desde su propio botón.');
        }

        $name = trim((string) ($data['nombre'] ?? ''));
        $objective = (int) ($data['objetivo_centavos'] ?? 0);
        $type = (string) ($data['tipo'] ?? 'ahorro');
        $targetDate = trim((string) ($data['fecha_objetivo'] ?? ''));
        $months = isset($data['plazo_meses']) && $data['plazo_meses'] !== '' ? (int) $data['plazo_meses'] : null;
        $active = !empty($data['activa']);

        if ($name === '' || $objective <= 0 || !in_array($type, self::META_TYPES, true)) {
            throw new InvalidArgumentException('Datos de la meta inválidos.');
        }

        if ($targetDate !== '' && !$this->validDate($targetDate)) {
            throw new InvalidArgumentException('La fecha objetivo no es válida.');
        }

        $this->repo->updateMeta(
            $metaId,
            (int) $user['id'],
            mb_substr($name, 0, 120),
            $type,
            $objective,
            $targetDate !== '' ? $targetDate : null,
            $months !== null ? max(1, min(120, $months)) : null,
            $active
        );
    }

    /**
     * @param array<string, mixed> $user
     */
    public function deactivateMeta(array $user, int $metaId): void
    {
        $meta = $this->requireMeta($user, $metaId);

        if ((int) $meta['es_emergencia'] === 1) {
            throw new RuntimeException('El fondo de emergencia no se puede desactivar.');
        }

        $this->repo->deactivateMeta($metaId, (int) $user['id']);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    /**
     * Aporta a una bolsa o meta. Con $alreadySaved el monto es dinero que la
     * persona ya tenía ahorrado: suma al saldo y al patrimonio, pero no sale
     * del ingreso del período (no descuenta del disponible ni del % de ahorro).
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function contribute(array $user, ?int $accountId, ?int $metaId, int $amount, string $date, bool $alreadySaved = false): array
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero.');
        }

        $meta = null;

        if ($metaId !== null) {
            $meta = $this->requireMeta($user, $metaId);

            if ((int) $meta['activa'] !== 1) {
                throw new RuntimeException('No se puede aportar a una meta inactiva.');
            }

            $accountId = (int) ($meta['cuenta_id'] ?? 0);
        }

        $account = $this->requireAccount($user, (int) $accountId);

        if ((int) $account['activa'] !== 1) {
            throw new RuntimeException('savings.errors.account_archived');
        }

        $utc = (new FinanceSupportService())->toUtc($date, $user);
        $this->assertCurrent($user, $utc);
        $currency = (string) $account['moneda'];

        $this->repo->createContribution(
            (int) $user['id'],
            (int) $account['id'],
            $metaId,
            $amount,
            $currency,
            $utc->format('Y-m-d H:i:s'),
            $alreadySaved
        );

        return $this->periodStatus($user) + ['currency' => $currency];
    }

    /**
     * @param array<string, mixed> $user
     * @return string moneda de la bolsa
     */
    public function withdraw(
        array $user,
        ?int $accountId,
        ?int $metaId,
        int $amount,
        string $reason,
        ?string $text,
        string $date
    ): string {
        return $this->withdrawEntry($user, $accountId, $metaId, $amount, $reason, $text, $date)['currency'];
    }

    /**
     * Igual que withdraw(), pero devuelve también el id del retiro.
     *
     * @param array<string, mixed> $user
     * @return array{id: int, currency: string}
     */
    public function withdrawEntry(
        array $user,
        ?int $accountId,
        ?int $metaId,
        int $amount,
        string $reason,
        ?string $text,
        string $date
    ): array {
        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero.');
        }

        if (!in_array($reason, self::WITHDRAW_REASONS, true)) {
            throw new InvalidArgumentException('Motivo de retiro inválido.');
        }

        $meta = null;

        if ($metaId !== null) {
            $meta = $this->requireMeta($user, $metaId);
            $accountId = (int) ($meta['cuenta_id'] ?? 0);
        }

        $account = $this->requireAccount($user, (int) $accountId);

        if ((int) $account['activa'] !== 1) {
            throw new RuntimeException('savings.errors.account_archived');
        }

        $utc = (new FinanceSupportService())->toUtc($date, $user);
        $this->assertCurrent($user, $utc);
        $currency = (string) $account['moneda'];
        $principalAmount = (new CurrencyService())->toPrincipal($user, $amount, $currency, $utc->format('Y-m-d'))['centavos'];
        $summary = $this->accountSummary($user, (int) $account['id']);

        if ($principalAmount > (int) $summary['saldo_principal_centavos']) {
            throw new RuntimeException('No puedes retirar más de lo disponible en esa bolsa.');
        }

        if ($metaId !== null) {
            $accumulated = $this->accumulated((int) $user['id'], $metaId);

            if ($principalAmount > $accumulated) {
                throw new RuntimeException('No puedes retirar más de lo disponible en esa meta.');
            }
        } else {
            $unassigned = (int) $summary['sin_asignar_principal_centavos'];

            if ($principalAmount > $unassigned) {
                throw new RuntimeException('savings.errors.assigned_withdraw');
            }
        }

        $id = $this->repo->createWithdrawal(
            (int) $user['id'],
            (int) $account['id'],
            $metaId,
            $amount,
            $currency,
            $reason,
            $text !== null && trim($text) !== '' ? mb_substr(trim($text), 0, 255) : null,
            $utc->format('Y-m-d H:i:s')
        );

        return ['id' => $id, 'currency' => $currency];
    }

    /**
     * Bolsas activas y sus metas, con saldo, para pagar una deuda con ahorro.
     *
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    public function debtSources(array $user): array
    {
        $userId = (int) $user['id'];
        $totals = $this->repo->totalsByMeta($userId);
        $metas = $this->repo->metasForUser($userId);
        $sources = [];

        foreach ($this->repo->accountsForUser($userId) as $account) {
            if ((int) $account['activa'] !== 1) {
                continue;
            }

            $accountId = (int) $account['id'];
            $assigned = 0;
            $accountMetas = [];

            foreach ($metas as $meta) {
                if ((int) ($meta['cuenta_id'] ?? 0) !== $accountId) {
                    continue;
                }

                $metaId = (int) $meta['id'];
                $balance = (int) ($totals[$metaId]['aportado'] ?? 0) - (int) ($totals[$metaId]['retirado'] ?? 0);
                $assigned += $balance;

                if ((int) ($meta['activa'] ?? 1) === 1 && $balance > 0) {
                    $accountMetas[] = ['id' => $metaId, 'nombre' => (string) $meta['nombre'], 'saldo_centavos' => $balance];
                }
            }

            $sources[] = [
                'id' => $accountId,
                'nombre' => (string) $account['nombre'],
                'moneda' => (string) $account['moneda'],
                'saldo_centavos' => (int) $account['saldo_centavos'],
                'sin_asignar_centavos' => max(0, (int) $account['saldo_principal_centavos'] - $assigned),
                'metas' => $accountMetas,
            ];
        }

        return $sources;
    }

    /**
     * @param array<string, mixed> $user
     */
    public function updateContribution(array $user, int $id, int $amount, string $date, ?bool $alreadySaved = null): void
    {
        $row = $this->repo->findContribution($id, (int) $user['id']);

        if ($row === null) {
            throw new RuntimeException('Ese movimiento no existe.');
        }

        if (!(new FinanceSupportService())->isCurrentPeriodDate((string) $row['fecha'], $user)) {
            throw new RuntimeException('Ese movimiento ya no se puede editar.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero.');
        }

        if ((int) ($row['es_reasignacion'] ?? 0) === 1) {
            throw new RuntimeException('savings.errors.internal_movement');
        }

        $this->assertMetaRowInCurrentAccount($user, $row);

        $utc = (new FinanceSupportService())->toUtc($date, $user);
        $this->assertCurrent($user, $utc);
        $metaId = $row['meta_id'] !== null ? (int) $row['meta_id'] : null;
        $accountId = $row['cuenta_id'] !== null ? (int) $row['cuenta_id'] : (int) ($this->repo->defaultAccount((int) $user['id'])['id'] ?? 0);
        $account = $this->requireAccount($user, $accountId);
        $summary = $this->accountSummary($user, $accountId);
        $oldPrincipal = (int) ($row['monto_principal_centavos'] ?? $row['monto_centavos']);
        $newPrincipal = (new CurrencyService())->toPrincipal($user, $amount, (string) $account['moneda'], $utc->format('Y-m-d'))['centavos'];
        $delta = $newPrincipal - $oldPrincipal;

        if ((int) $summary['saldo_principal_centavos'] + $delta < 0) {
            throw new RuntimeException('savings.errors.account_negative');
        }

        if ($metaId !== null) {
            if ($this->accumulated((int) $user['id'], $metaId) + $delta < 0) {
                throw new RuntimeException('El ajuste dejaría la meta en negativo.');
            }
        } elseif ((int) $summary['sin_asignar_principal_centavos'] + $delta < 0) {
            throw new RuntimeException('savings.errors.below_goals');
        }

        $this->repo->updateContribution(
            $id,
            (int) $user['id'],
            $amount,
            $utc->format('Y-m-d H:i:s'),
            $alreadySaved ?? (int) ($row['es_saldo_inicial'] ?? 0) === 1
        );
    }

    /**
     * @param array<string, mixed> $user
     */
    public function deleteContribution(array $user, int $id): void
    {
        $row = $this->repo->findContribution($id, (int) $user['id']);

        if ($row === null) {
            throw new RuntimeException('Ese movimiento no existe.');
        }

        if (!(new FinanceSupportService())->isCurrentPeriodDate((string) $row['fecha'], $user)) {
            throw new RuntimeException('Ese movimiento ya no se puede borrar.');
        }

        if ((int) ($row['es_reasignacion'] ?? 0) === 1) {
            throw new RuntimeException('savings.errors.internal_movement');
        }

        $this->assertMetaRowInCurrentAccount($user, $row);

        $metaId = $row['meta_id'] !== null ? (int) $row['meta_id'] : null;
        $accountId = $row['cuenta_id'] !== null ? (int) $row['cuenta_id'] : (int) ($this->repo->defaultAccount((int) $user['id'])['id'] ?? 0);
        $summary = $this->accountSummary($user, $accountId);
        $oldPrincipal = (int) ($row['monto_principal_centavos'] ?? $row['monto_centavos']);

        if ((int) $summary['saldo_principal_centavos'] - $oldPrincipal < 0) {
            throw new RuntimeException('savings.errors.account_negative');
        }

        if ($metaId !== null) {
            if ($this->accumulated((int) $user['id'], $metaId) - $oldPrincipal < 0) {
                throw new RuntimeException('El ajuste dejaría la meta en negativo.');
            }
        } elseif ((int) $summary['sin_asignar_principal_centavos'] - $oldPrincipal < 0) {
            throw new RuntimeException('savings.errors.below_goals');
        }

        $this->repo->deleteContribution($id, (int) $user['id']);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function updateWithdrawal(
        array $user,
        int $id,
        int $amount,
        string $reason,
        ?string $text,
        string $date
    ): void {
        $row = $this->repo->findWithdrawal($id, (int) $user['id']);

        if ($row === null) {
            throw new RuntimeException('Ese movimiento no existe.');
        }

        if (!(new FinanceSupportService())->isCurrentPeriodDate((string) $row['fecha'], $user)) {
            throw new RuntimeException('Ese movimiento ya no se puede editar.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero.');
        }

        if (!in_array($reason, self::WITHDRAW_REASONS, true)) {
            throw new InvalidArgumentException('Motivo de retiro inválido.');
        }

        $utc = (new FinanceSupportService())->toUtc($date, $user);
        $this->assertCurrent($user, $utc);

        if ((int) ($row['es_reasignacion'] ?? 0) === 1) {
            throw new RuntimeException('savings.errors.internal_movement');
        }

        $this->assertMetaRowInCurrentAccount($user, $row);

        $metaId = $row['meta_id'] !== null ? (int) $row['meta_id'] : null;
        $accountId = $row['cuenta_id'] !== null ? (int) $row['cuenta_id'] : (int) ($this->repo->defaultAccount((int) $user['id'])['id'] ?? 0);
        $account = $this->requireAccount($user, $accountId);
        $summary = $this->accountSummary($user, $accountId);
        $oldPrincipal = (int) ($row['monto_principal_centavos'] ?? $row['monto_centavos']);
        $newPrincipal = (new CurrencyService())->toPrincipal($user, $amount, (string) $account['moneda'], $utc->format('Y-m-d'))['centavos'];
        // Un retiro mayor resta saldo: el delta se descuenta.
        $delta = $newPrincipal - $oldPrincipal;

        if ((int) $summary['saldo_principal_centavos'] - $delta < 0) {
            throw new RuntimeException('savings.errors.account_negative');
        }

        if ($metaId !== null) {
            if ($this->accumulated((int) $user['id'], $metaId) - $delta < 0) {
                throw new RuntimeException('El ajuste dejaría la meta en negativo.');
            }
        } elseif ((int) $summary['sin_asignar_principal_centavos'] - $delta < 0) {
            throw new RuntimeException('savings.errors.below_goals');
        }

        $this->repo->updateWithdrawal(
            $id,
            (int) $user['id'],
            $amount,
            $reason,
            $text !== null && trim($text) !== '' ? mb_substr(trim($text), 0, 255) : null,
            $utc->format('Y-m-d H:i:s')
        );
    }

    /**
     * @param array<string, mixed> $user
     */
    public function deleteWithdrawal(array $user, int $id): void
    {
        $row = $this->repo->findWithdrawal($id, (int) $user['id']);

        if ($row === null) {
            throw new RuntimeException('Ese movimiento no existe.');
        }

        if (!(new FinanceSupportService())->isCurrentPeriodDate((string) $row['fecha'], $user)) {
            throw new RuntimeException('Ese movimiento ya no se puede borrar.');
        }

        if ((int) ($row['es_reasignacion'] ?? 0) === 1) {
            throw new RuntimeException('savings.errors.internal_movement');
        }

        $this->assertMetaRowInCurrentAccount($user, $row);

        // Si ese retiro pagó una deuda, el pago se deshace con él.
        $payment = (new DebtRepository())->paymentForWithdrawal($id, (int) $user['id']);
        $db = Database::connection();
        $db->beginTransaction();

        try {
            if ($payment !== null) {
                (new DebtRepository())->revertPayment($payment);
            }

            $this->repo->deleteWithdrawal($id, (int) $user['id']);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $user
     */
    public function ensureDefaultAccount(array $user): array
    {
        return $this->repo->ensureDefaultAccount(
            (int) $user['id'],
            (string) ($user['moneda_activa'] ?? 'MXN')
        );
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     */
    public function createAccount(array $user, array $data): int
    {
        $name = trim((string) ($data['nombre'] ?? ''));
        $type = (string) ($data['tipo'] ?? 'otro');
        $institution = trim((string) ($data['institucion'] ?? ''));
        $currency = (new CurrencyService())->validateCode(
            trim((string) ($data['moneda'] ?? '')) !== ''
                ? (string) $data['moneda']
                : (string) ($user['moneda_activa'] ?? 'MXN')
        );
        $initial = (int) ($data['saldo_inicial_centavos'] ?? 0);
        $default = !empty($data['predeterminada']);
        $types = ['banco', 'efectivo', 'cooperativa', 'otro'];

        if ($name === '') {
            throw new InvalidArgumentException('savings.errors.account_name');
        }

        if (!in_array($type, $types, true)) {
            throw new InvalidArgumentException('savings.errors.account_data');
        }

        if ($initial < 0) {
            throw new InvalidArgumentException('savings.errors.initial_negative');
        }

        $existing = $this->repo->accountsForUser((int) $user['id']);
        $default = $default || $existing === [];

        $db = Database::connection();

        try {
            $db->beginTransaction();
            $repo = new SavingsRepository($db);
            $accountId = $repo->createAccount(
                (int) $user['id'],
                mb_substr($name, 0, 120),
                $type,
                $institution !== '' ? mb_substr($institution, 0, 120) : null,
                $currency,
                $default
            );

            if ($initial > 0) {
                $repo->createContribution(
                    (int) $user['id'],
                    $accountId,
                    null,
                    $initial,
                    $currency,
                    gmdate('Y-m-d H:i:s'),
                    true
                );
            }

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        return $accountId;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     */
    public function updateAccount(array $user, int $id, array $data): void
    {
        $account = $this->requireAccount($user, $id);
        $name = trim((string) ($data['nombre'] ?? ''));
        $type = (string) ($data['tipo'] ?? 'otro');
        $institution = trim((string) ($data['institucion'] ?? ''));
        $active = !empty($data['activa']);
        $default = (!empty($data['predeterminada']) || (int) ($account['es_predeterminada'] ?? 0) === 1) && $active;
        $types = ['banco', 'efectivo', 'cooperativa', 'otro'];
        if ($name === '' || !in_array($type, $types, true)) {
            throw new InvalidArgumentException('savings.errors.account_data');
        }

        if (!$active) {
            $summary = $this->accountSummary($user, $id);

            if ((int) $summary['saldo_principal_centavos'] !== 0 || (int) $summary['metas_count'] > 0) {
                throw new RuntimeException('savings.errors.archive_not_empty');
            }

            if ((int) ($account['es_predeterminada'] ?? 0) === 1) {
                throw new RuntimeException('savings.errors.archive_default');
            }
        }

        $this->repo->updateAccount(
            $id,
            (int) $user['id'],
            mb_substr($name, 0, 120),
            $type,
            $institution !== '' ? mb_substr($institution, 0, 120) : null,
            $active,
            $default
        );
    }

    /**
     * @param array<string, mixed> $user
     */
    public function archiveAccount(array $user, int $id): void
    {
        $account = $this->requireAccount($user, $id);
        $summary = $this->accountSummary($user, $id);

        if ((int) $summary['saldo_principal_centavos'] !== 0 || (int) $summary['metas_count'] > 0) {
            throw new RuntimeException('savings.errors.archive_not_empty');
        }

        if ((int) ($account['es_predeterminada'] ?? 0) === 1) {
            throw new RuntimeException('savings.errors.archive_default');
        }

        $this->repo->updateAccount(
            $id,
            (int) $user['id'],
            (string) $account['nombre'],
            (string) $account['tipo'],
            $account['institucion'] !== null ? (string) $account['institucion'] : null,
            false,
            false
        );
    }

    /**
     * @param array<string, mixed> $user
     */
    public function transfer(array $user, int $originId, int $destinationId, int $amount, string $date, ?string $note): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero.');
        }

        if ($originId === $destinationId) {
            throw new InvalidArgumentException('savings.errors.same_account');
        }

        $origin = $this->requireAccount($user, $originId);
        $destination = $this->requireAccount($user, $destinationId);

        if ((int) $origin['activa'] !== 1 || (int) $destination['activa'] !== 1) {
            throw new RuntimeException('savings.errors.accounts_inactive');
        }

        if ((string) $origin['moneda'] !== (string) $destination['moneda']) {
            throw new RuntimeException('savings.errors.currency_mismatch');
        }

        $utc = (new FinanceSupportService())->toUtc($date, $user);
        $this->assertCurrent($user, $utc);
        $currency = (string) $origin['moneda'];
        $principalAmount = (new CurrencyService())->toPrincipal($user, $amount, $currency, $utc->format('Y-m-d'))['centavos'];
        $summary = $this->accountSummary($user, $originId);

        if ($principalAmount > (int) $summary['sin_asignar_principal_centavos']) {
            throw new RuntimeException('savings.errors.assigned_transfer');
        }

        $this->repo->createTransfer(
            (int) $user['id'],
            $originId,
            $destinationId,
            null,
            'transferencia',
            $amount,
            $currency,
            $utc->format('Y-m-d H:i:s'),
            $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 255) : null
        );
    }

    /**
     * @param array<string, mixed> $user
     */
    public function assignToMeta(array $user, int $accountId, int $metaId, int $amount, string $date): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero.');
        }

        $account = $this->requireAccount($user, $accountId);
        $meta = $this->requireMeta($user, $metaId);

        if ((int) ($meta['cuenta_id'] ?? 0) !== $accountId) {
            throw new InvalidArgumentException('savings.errors.meta_account');
        }

        if ((int) $meta['activa'] !== 1 || (int) $account['activa'] !== 1) {
            throw new RuntimeException('savings.errors.meta_account_inactive');
        }

        $utc = (new FinanceSupportService())->toUtc($date, $user);
        $this->assertCurrent($user, $utc);
        $currency = (string) $account['moneda'];
        $principalAmount = (new CurrencyService())->toPrincipal($user, $amount, $currency, $utc->format('Y-m-d'))['centavos'];
        $summary = $this->accountSummary($user, $accountId);

        if ($principalAmount > (int) $summary['sin_asignar_principal_centavos']) {
            throw new RuntimeException('savings.errors.unassigned_insufficient');
        }

        $db = Database::connection();

        try {
            $db->beginTransaction();
            $repo = new SavingsRepository($db);
            $repo->createWithdrawal(
                (int) $user['id'],
                $accountId,
                null,
                $amount,
                $currency,
                'reasignacion',
                'Asignación a meta',
                $utc->format('Y-m-d H:i:s'),
                true
            );
            $repo->createContribution(
                (int) $user['id'],
                $accountId,
                $metaId,
                $amount,
                $currency,
                $utc->format('Y-m-d H:i:s'),
                false,
                true
            );
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $user
     */
    public function moveMeta(array $user, int $metaId, int $destinationId, string $date): void
    {
        $meta = $this->requireMeta($user, $metaId);
        $sourceId = (int) ($meta['cuenta_id'] ?? 0);

        if ($sourceId <= 0) {
            throw new RuntimeException('savings.errors.meta_no_account');
        }

        if ($sourceId === $destinationId) {
            throw new InvalidArgumentException('savings.errors.meta_same_account');
        }

        $source = $this->requireAccount($user, $sourceId);
        $destination = $this->requireAccount($user, $destinationId);

        if ((int) $source['activa'] !== 1 || (int) $destination['activa'] !== 1) {
            throw new RuntimeException('savings.errors.accounts_inactive');
        }

        if ((string) $source['moneda'] !== (string) $destination['moneda']) {
            throw new RuntimeException('savings.errors.currency_mismatch');
        }

        $utc = (new FinanceSupportService())->toUtc($date, $user);
        $this->assertCurrent($user, $utc);
        $accumulated = $this->accumulated((int) $user['id'], $metaId);
        $currency = (string) $source['moneda'];

        $db = Database::connection();

        try {
            $db->beginTransaction();
            $repo = new SavingsRepository($db);

            if ($accumulated > 0) {
                $summary = $this->accountSummary($user, $sourceId);

                if ($accumulated > (int) $summary['saldo_principal_centavos']) {
                    throw new RuntimeException('savings.errors.move_insufficient');
                }

                $principal = (string) ($user['moneda_activa'] ?? 'MXN');
                $nativeAmount = $accumulated;

                if ($currency !== $principal) {
                    $nativeAmount = (new CurrencyService())->convert(
                        $user,
                        $accumulated,
                        $principal,
                        $currency,
                        $utc->format('Y-m-d')
                    )['centavos'];
                }

                $repo->createTransfer(
                    (int) $user['id'],
                    $sourceId,
                    $destinationId,
                    $metaId,
                    'mover_meta',
                    $nativeAmount,
                    $currency,
                    $utc->format('Y-m-d H:i:s'),
                    'Mover meta: ' . (string) ($meta['nombre'] ?? '')
                );
            }

            $repo->updateMetaAccount($metaId, (int) $user['id'], $destinationId);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Crea la bolsa propia de un deseo y su meta. El llamador controla la
     * transacción.
     *
     * @param array<string, mixed> $user
     */
    public function createWishGoal(
        array $user,
        string $bagName,
        string $goalName,
        int $objective,
        string $currency,
        ?string $targetDate
    ): int {
        if ($objective <= 0) {
            throw new InvalidArgumentException('El objetivo debe ser mayor que cero.');
        }

        $currency = (new CurrencyService())->validateCode($currency);
        // La bolsa del deseo nunca debe quedar como predeterminada.
        $this->repo->ensureDefaultAccount((int) $user['id'], (string) ($user['moneda_activa'] ?? 'MXN'));
        $accountId = $this->repo->createAccount(
            (int) $user['id'],
            mb_substr($bagName, 0, 120),
            'otro',
            null,
            $currency,
            false
        );

        return $this->repo->createMeta(
            (int) $user['id'],
            $accountId,
            mb_substr($goalName, 0, 120),
            'deseo',
            $objective,
            $currency,
            $targetDate,
            null
        );
    }

    /**
     * Mantiene la meta de un deseo alineada con el deseo editado.
     *
     * @param array<string, mixed> $user
     */
    public function syncWishGoal(
        array $user,
        int $metaId,
        string $name,
        int $objective,
        string $currency,
        ?string $targetDate
    ): void {
        $meta = $this->repo->findMeta($metaId, (int) $user['id']);

        if ($meta === null || $objective <= 0) {
            return;
        }

        $metaCurrency = (string) ($meta['moneda'] ?? $currency);

        if ($metaCurrency !== $currency) {
            try {
                $objective = (new CurrencyService())->convert($user, $objective, $currency, $metaCurrency, gmdate('Y-m-d'))['centavos'];
            } catch (RuntimeException) {
                $objective = (int) $meta['objetivo_centavos'];
            }
        }

        $this->repo->updateMeta(
            $metaId,
            (int) $user['id'],
            mb_substr($name, 0, 120),
            (string) $meta['tipo'],
            max(1, $objective),
            $targetDate,
            $meta['plazo_meses'] !== null ? (int) $meta['plazo_meses'] : null,
            (int) $meta['activa'] === 1
        );
    }

    /**
     * Renombra la bolsa de un deseo solo si conserva el nombre automático.
     *
     * @param array<string, mixed> $user
     */
    public function renameWishBag(array $user, int $metaId, string $oldName, string $newName): void
    {
        $meta = $this->repo->findMeta($metaId, (int) $user['id']);
        $account = $meta !== null && !empty($meta['cuenta_id'])
            ? $this->repo->findAccount((int) $meta['cuenta_id'], (int) $user['id'])
            : null;

        if ($account === null || (string) $account['nombre'] !== mb_substr($oldName, 0, 120) || $oldName === $newName) {
            return;
        }

        $this->repo->updateAccount(
            (int) $account['id'],
            (int) $user['id'],
            mb_substr($newName, 0, 120),
            (string) $account['tipo'],
            $account['institucion'] !== null ? (string) $account['institucion'] : null,
            (int) $account['activa'] === 1,
            (int) $account['es_predeterminada'] === 1
        );
    }

    /**
     * Reactiva la meta de un deseo si el usuario la desactivó desde /ahorro.
     *
     * @param array<string, mixed> $user
     */
    public function reactivateMeta(array $user, int $metaId): void
    {
        $meta = $this->requireMeta($user, $metaId);

        if ((int) $meta['activa'] === 1) {
            return;
        }

        $this->repo->updateMeta(
            $metaId,
            (int) $user['id'],
            (string) $meta['nombre'],
            (string) $meta['tipo'],
            (int) $meta['objetivo_centavos'],
            $meta['fecha_objetivo'] !== null ? (string) $meta['fecha_objetivo'] : null,
            $meta['plazo_meses'] !== null ? (int) $meta['plazo_meses'] : null,
            true
        );
    }

    /**
     * Acumulado de una meta en la moneda principal del usuario.
     *
     * @param array<string, mixed> $user
     */
    public function metaBalance(array $user, int $metaId): int
    {
        return $this->accumulated((int) $user['id'], $metaId);
    }

    /**
     * Al comprar un deseo: retira de su meta hasta $maxPrincipal (moneda
     * principal), desactiva la meta si queda en cero y archiva la bolsa si
     * ya no tiene saldo. Devuelve lo retirado en moneda principal.
     *
     * @param array<string, mixed> $user
     */
    public function spendWishGoal(array $user, int $metaId, int $maxPrincipal, string $note, ?int $gastoId = null): int
    {
        $meta = $this->requireMeta($user, $metaId);
        $account = $this->requireAccount($user, (int) ($meta['cuenta_id'] ?? 0));
        $available = $this->accumulated((int) $user['id'], $metaId);
        $target = min($available, max(0, $maxPrincipal));
        $withdrawn = 0;

        if ($target > 0 && (int) $account['activa'] === 1) {
            $principal = (string) ($user['moneda_activa'] ?? 'MXN');
            $currency = (string) $account['moneda'];
            $amount = $target;
            $today = gmdate('Y-m-d');

            if ($currency !== $principal) {
                $currencyService = new CurrencyService();
                $amount = $currencyService->convert($user, $target, $principal, $currency, $today)['centavos'];

                // El redondeo de ida y vuelta no debe pasar del acumulado.
                for ($i = 0; $i < 100 && $amount > 0
                    && $currencyService->toPrincipal($user, $amount, $currency, $today)['centavos'] > $target; $i++) {
                    $amount--;
                }
            }

            if ($amount > 0) {
                $this->withdraw($user, null, $metaId, $amount, 'meta_cumplida', $note, 'now');
                $withdrawn = $target;

                // Si la compra registró un gasto, el retiro queda ligado a él:
                // al borrar el gasto, el dinero vuelve a la bolsa.
                if ($gastoId !== null && $gastoId > 0) {
                    $this->repo->linkLastWithdrawalToGasto((int) $user['id'], $metaId, $gastoId);
                }
            }
        }

        if ($this->accumulated((int) $user['id'], $metaId) <= 0) {
            $this->repo->deactivateMeta($metaId, (int) $user['id']);
        }

        $summary = $this->accountSummary($user, (int) $account['id']);

        if (
            (int) $summary['saldo_principal_centavos'] === 0
            && (int) ($account['es_predeterminada'] ?? 0) !== 1
            && (int) $account['activa'] === 1
        ) {
            $this->repo->updateAccount(
                (int) $account['id'],
                (int) $user['id'],
                (string) $account['nombre'],
                (string) $account['tipo'],
                $account['institucion'] !== null ? (string) $account['institucion'] : null,
                false,
                false
            );
        }

        return $withdrawn;
    }

    /**
     * @param array<string, mixed> $user
     */
    public function updateEmergency(array $user, int $metaId): void
    {
        (new EmergencyFundService())->update($user, $metaId);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function periodStatus(array $user): array
    {
        $support = new FinanceSupportService();
        $period = $support->period($user);
        $assigned = $support->assignedForType($user, 'ahorro');
        $contributed = $this->repo->sumPeriodContributions((int) $user['id'], $period['inicio'], $period['fin']);

        return [
            'assigned_centavos' => $assigned,
            'contributed_centavos' => $contributed,
            'available_centavos' => max(0, $assigned - $contributed),
            'over_percentage' => $contributed > $assigned,
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function requireAccount(array $user, int $accountId): array
    {
        if ($accountId <= 0) {
            $default = $this->repo->defaultAccount((int) $user['id']);
            $accountId = (int) ($default['id'] ?? 0);

            if ($accountId <= 0) {
                $default = $this->repo->ensureDefaultAccount(
                    (int) $user['id'],
                    (string) ($user['moneda_activa'] ?? 'MXN')
                );
                $accountId = (int) ($default['id'] ?? 0);
            }
        }

        $account = $this->repo->findAccount($accountId, (int) $user['id']);

        if ($account === null) {
            throw new RuntimeException('savings.errors.account_not_found');
        }

        return $account;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function accountSummary(array $user, int $accountId): array
    {
        $accounts = $this->repo->accountsForUser((int) $user['id']);
        $account = null;

        foreach ($accounts as $row) {
            if ((int) $row['id'] === $accountId) {
                $account = $row;
                break;
            }
        }

        if ($account === null) {
            throw new RuntimeException('savings.errors.account_not_found');
        }

        $totals = $this->repo->totalsByMeta((int) $user['id']);
        $metas = $this->repo->metasForUser((int) $user['id']);
        $assigned = 0;
        $count = 0;

        foreach ($metas as $meta) {
            if ((int) ($meta['cuenta_id'] ?? 0) !== $accountId) {
                continue;
            }

            $metaId = (int) $meta['id'];
            $assigned += (int) ($totals[$metaId]['aportado'] ?? 0)
                - (int) ($totals[$metaId]['retirado'] ?? 0);
            $count++;
        }

        $account['asignado_principal_centavos'] = $assigned;
        $account['sin_asignar_principal_centavos'] = (int) $account['saldo_principal_centavos'] - $assigned;
        $account['metas_count'] = $count;

        return $account;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function requireMeta(array $user, int $metaId): array
    {
        $meta = $this->repo->findMeta($metaId, (int) $user['id']);

        if ($meta === null) {
            throw new RuntimeException('La meta no existe.');
        }

        return $meta;
    }

    /**
     * Un movimiento de meta registrado antes de "Mover meta" quedó en la bolsa
     * de origen. Editarlo ajustaría el saldo de esa bolsa y lo asignado de la
     * nueva a la vez, así que se bloquea.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $row
     */
    private function assertMetaRowInCurrentAccount(array $user, array $row): void
    {
        if ($row['meta_id'] === null || $row['cuenta_id'] === null) {
            return;
        }

        $meta = $this->repo->findMeta((int) $row['meta_id'], (int) $user['id']);

        if ($meta !== null && (int) ($meta['cuenta_id'] ?? 0) !== (int) $row['cuenta_id']) {
            throw new RuntimeException('savings.errors.moved_movement');
        }
    }

    private function accumulated(int $userId, ?int $metaId): int
    {
        return $this->repo->sumContributions($userId, $metaId) - $this->repo->sumWithdrawals($userId, $metaId);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function remainingMonths(array $meta): ?int
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if (!empty($meta['fecha_objetivo'])) {
            $target = new DateTimeImmutable((string) $meta['fecha_objetivo'], new DateTimeZone('UTC'));
            $days = (int) ceil(($target->getTimestamp() - $now->getTimestamp()) / 86400);

            return max(0, (int) ceil($days / 30.44));
        }

        if (!empty($meta['plazo_meses'])) {
            $created = new DateTimeImmutable((string) $meta['created_at'], new DateTimeZone('UTC'));
            $elapsed = max(0, (int) floor(($now->getTimestamp() - $created->getTimestamp()) / (86400 * 30.44)));

            return max(0, (int) $meta['plazo_meses'] - $elapsed);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function monthlySuggestion(array $meta, int $accumulated): ?int
    {
        $objective = (int) $meta['objetivo_centavos'];

        if ($objective <= 0) {
            return null;
        }

        $remaining = max(0, $objective - $accumulated);

        if ($remaining === 0) {
            return 0;
        }

        $months = null;

        if (!empty($meta['fecha_objetivo'])) {
            $target = new DateTimeImmutable((string) $meta['fecha_objetivo'], new DateTimeZone('UTC'));
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $days = max(1, (int) ceil(($target->getTimestamp() - $now->getTimestamp()) / 86400));
            $months = max(1, (int) ceil($days / 30.44));
        } elseif (!empty($meta['plazo_meses'])) {
            $created = new DateTimeImmutable((string) $meta['created_at'], new DateTimeZone('UTC'));
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $elapsed = max(0, (int) floor(($now->getTimestamp() - $created->getTimestamp()) / (86400 * 30.44)));
            $months = max(1, (int) $meta['plazo_meses'] - $elapsed);
        }

        if ($months === null) {
            return null;
        }

        return (int) ceil($remaining / $months);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function assertCurrent(array $user, DateTimeImmutable $date): void
    {
        $period = (new FinanceSupportService())->period($user);

        if ($date < $period['inicio_dt'] || $date > $period['fin_dt']) {
            throw new RuntimeException('Solo puedes registrar movimientos en el período actual.');
        }
    }

    private function validDate(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
