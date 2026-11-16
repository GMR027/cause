<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Repositories\UserRepository;
use PDO;
use RuntimeException;

/**
 * Recalcula los montos convertidos a la moneda principal.
 *
 * Se ejecuta al cambiar la moneda principal o al corregir tasas de cambio.
 */
final class CurrencyRecalculationService
{
    private PDO $db;

    private CurrencyService $currency;

    public function __construct(?PDO $db = null, ?CurrencyService $currency = null)
    {
        $this->db = $db ?? Database::connection();
        $this->currency = $currency ?? new CurrencyService();
    }

    /**
     * @param array<string, mixed> $user
     */
    public function forUser(array $user, ?string $oldPrincipal = null): void
    {
        $userId = (int) $user['id'];
        $principal = $this->currency->principal($user);

        $this->recalcSimple($userId, 'no_controlado', 'monto_centavos', 'monto_principal_centavos', 'created_at', $principal);
        $this->recalcSimple($userId, 'gastos_recurrentes', 'monto_centavos', 'monto_principal_centavos', 'created_at', $principal);
        $this->recalcSimple($userId, 'plantillas_gasto', 'monto_centavos', 'monto_principal_centavos', 'created_at', $principal);
        $this->recalcSimple($userId, 'movimientos', 'monto_centavos', 'monto_principal_centavos', 'fecha', $principal);
        $this->recalcSimple($userId, 'aportaciones_ahorro', 'monto_centavos', 'monto_principal_centavos', 'fecha', $principal);
        $this->recalcSimple($userId, 'retiros_ahorro', 'monto_centavos', 'monto_principal_centavos', 'fecha', $principal);
        $this->recalcSimple($userId, 'aportaciones_inversion', 'monto_centavos', 'monto_principal_centavos', 'fecha', $principal);
        $this->recalcSimple($userId, 'retiros_inversion', 'monto_centavos', 'monto_principal_centavos', 'fecha', $principal);
        $this->recalcSimple($userId, 'inversiones_rendimientos', 'monto_centavos', 'monto_principal_centavos', 'fecha', $principal);
        $this->recalcSimple($userId, 'donaciones', 'monto_centavos', 'monto_principal_centavos', 'fecha', $principal);
        $this->recalcSimple($userId, 'compartidos', 'monto_centavos', 'monto_principal_centavos', 'fecha', $principal);
        $this->recalcSimple($userId, 'pagos_deuda', 'monto_centavos', 'monto_principal_centavos', 'fecha', $principal);
        $this->recalcSimple($userId, 'lista_deseos', 'monto_estimado_centavos', 'monto_estimado_principal_centavos', 'created_at', $principal);
        $this->recalcSimple($userId, 'metas_ahorro', 'objetivo_centavos', 'objetivo_principal_centavos', 'created_at', $principal);
        $this->recalcGastos($userId, $principal);
        $this->recalcDeudas($userId, $principal);
        $this->recalcInvestments($userId, $principal);
        $this->recalcIncomesAndAssignments($userId, $principal);

        $this->db->prepare(
            'UPDATE asignaciones SET monto_principal_centavos = monto_centavos WHERE user_id = ?'
        )->execute([$userId]);

        if ($oldPrincipal !== null && $oldPrincipal !== $principal) {
            $this->recalcCash($user, $oldPrincipal, $principal);
        }
    }

    public function forAll(): void
    {
        $stmt = $this->db->query('SELECT * FROM users');

        foreach ($stmt->fetchAll() as $user) {
            $this->forUser($user);
        }
    }

    /**
     * @param array<string, mixed> $user
     */
    private function recalcCash(array $user, string $oldPrincipal, string $newPrincipal): void
    {
        $cash = (int) ($user['efectivo_manual_centavos'] ?? 0);

        if ($cash === 0) {
            return;
        }

        try {
            $conversion = $this->currency->convert($user, $cash, $oldPrincipal, $newPrincipal, gmdate('Y-m-d'));
            (new UserRepository())->updateCash((int) $user['id'], $conversion['centavos']);
        } catch (RuntimeException) {
            // Sin tasa: se conserva el valor anterior y el usuario puede corregirlo.
        }
    }

    private function recalcSimple(
        int $userId,
        string $table,
        string $amountColumn,
        string $targetColumn,
        string $dateColumn,
        string $principal
    ): void {
        $allowed = [
            'ingresos', 'no_controlado', 'gastos_recurrentes', 'plantillas_gasto',
            'movimientos', 'aportaciones_ahorro', 'retiros_ahorro',
            'aportaciones_inversion', 'retiros_inversion', 'inversiones_rendimientos', 'donaciones', 'compartidos',
            'pagos_deuda', 'lista_deseos', 'metas_ahorro',
        ];

        if (!in_array($table, $allowed, true)) {
            return;
        }

        $stmt = $this->db->prepare(
            "SELECT id, {$amountColumn} AS amount, moneda, {$dateColumn} AS rate_date
             FROM {$table} WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        $update = $this->db->prepare("UPDATE {$table} SET {$targetColumn} = ? WHERE id = ?");

        foreach ($stmt->fetchAll() as $row) {
            try {
                $conversion = $this->currency->convert(
                    ['id' => $userId, 'moneda_activa' => $principal],
                    (int) $row['amount'],
                    (string) $row['moneda'],
                    $principal,
                    substr((string) $row['rate_date'], 0, 10)
                );
                $update->execute([$conversion['centavos'], (int) $row['id']]);
            } catch (RuntimeException) {
                $update->execute([null, (int) $row['id']]);
            }
        }
    }

    private function recalcIncomesAndAssignments(int $userId, string $principal): void
    {
        $user = (new \Cauce\Repositories\UserRepository())->findById($userId);

        if ($user === null) {
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT id, monto_centavos, monto_principal_centavos, moneda, fecha FROM ingresos WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        $updateIncome = $this->db->prepare(
            'UPDATE ingresos SET monto_principal_centavos = ? WHERE id = ?'
        );
        $scaleAssignments = $this->db->prepare(
            'UPDATE asignaciones SET
                monto_centavos = CAST(ROUND(monto_centavos * ?) AS UNSIGNED),
                monto_principal_centavos = CAST(ROUND(monto_principal_centavos * ?) AS UNSIGNED),
                moneda = ?
             WHERE ingreso_id = ?'
        );

        foreach ($stmt->fetchAll() as $row) {
            try {
                $newPrincipal = $this->currency->toPrincipal(
                    $user,
                    (int) $row['monto_centavos'],
                    (string) $row['moneda'],
                    substr((string) $row['fecha'], 0, 10)
                )['centavos'];
            } catch (RuntimeException) {
                continue;
            }

            $oldPrincipal = (int) ($row['monto_principal_centavos'] ?? 0);
            $updateIncome->execute([$newPrincipal, (int) $row['id']]);

            if ($oldPrincipal <= 0 || $oldPrincipal === $newPrincipal) {
                continue;
            }

            $ratio = $newPrincipal / $oldPrincipal;
            $scaleAssignments->execute([$ratio, $ratio, $principal, (int) $row['id']]);
        }
    }

    private function recalcGastos(int $userId, string $principal): void
    {
        $stmt = $this->db->prepare(
            'SELECT id, monto_centavos, monto_total_compartido_centavos, moneda, fecha
             FROM gastos WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        $update = $this->db->prepare(
            'UPDATE gastos SET monto_principal_centavos = ?, monto_total_compartido_principal_centavos = ? WHERE id = ?'
        );

        foreach ($stmt->fetchAll() as $row) {
            try {
                $amount = $this->currency->convert(
                    ['id' => $userId, 'moneda_activa' => $principal],
                    (int) $row['monto_centavos'],
                    (string) $row['moneda'],
                    $principal,
                    substr((string) $row['fecha'], 0, 10)
                )['centavos'];
                $shared = $row['monto_total_compartido_centavos'] !== null
                    ? $this->currency->convert(
                        ['id' => $userId, 'moneda_activa' => $principal],
                        (int) $row['monto_total_compartido_centavos'],
                        (string) $row['moneda'],
                        $principal,
                        substr((string) $row['fecha'], 0, 10)
                    )['centavos']
                    : null;
                $update->execute([$amount, $shared, (int) $row['id']]);
            } catch (RuntimeException) {
                $update->execute([null, null, (int) $row['id']]);
            }
        }
    }

    private function recalcDeudas(int $userId, string $principal): void
    {
        $stmt = $this->db->prepare(
            'SELECT id, monto_total_centavos, saldo_actual_centavos, pago_minimo_centavos, moneda, created_at
             FROM deudas WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        $update = $this->db->prepare(
            'UPDATE deudas SET monto_total_principal_centavos = ?,
                saldo_actual_principal_centavos = ?, pago_minimo_principal_centavos = ?
             WHERE id = ?'
        );

        foreach ($stmt->fetchAll() as $row) {
            try {
                $context = ['id' => $userId, 'moneda_activa' => $principal];
                $date = substr((string) $row['created_at'], 0, 10);
                $total = $this->currency->convert($context, (int) $row['monto_total_centavos'], (string) $row['moneda'], $principal, $date)['centavos'];
                $balance = $this->currency->convert($context, (int) $row['saldo_actual_centavos'], (string) $row['moneda'], $principal, $date)['centavos'];
                $minimum = $row['pago_minimo_centavos'] !== null
                    ? $this->currency->convert($context, (int) $row['pago_minimo_centavos'], (string) $row['moneda'], $principal, $date)['centavos']
                    : null;
                $update->execute([$total, $balance, $minimum, (int) $row['id']]);
            } catch (RuntimeException) {
                $update->execute([null, null, null, (int) $row['id']]);
            }
        }
    }

    private function recalcInvestments(int $userId, string $principal): void
    {
        $stmt = $this->db->prepare(
            'SELECT id, valor_actual_centavos, moneda, COALESCE(fecha_valor_actual, created_at) AS rate_date
             FROM inversiones_fondos WHERE user_id = ? AND valor_actual_centavos IS NOT NULL'
        );
        $stmt->execute([$userId]);
        $update = $this->db->prepare(
            'UPDATE inversiones_fondos SET valor_actual_principal_centavos = ? WHERE id = ?'
        );

        foreach ($stmt->fetchAll() as $row) {
            try {
                $conversion = $this->currency->convert(
                    ['id' => $userId, 'moneda_activa' => $principal],
                    (int) $row['valor_actual_centavos'],
                    (string) $row['moneda'],
                    $principal,
                    substr((string) $row['rate_date'], 0, 10)
                );
                $update->execute([$conversion['centavos'], (int) $row['id']]);
            } catch (RuntimeException) {
                $update->execute([null, (int) $row['id']]);
            }
        }
    }
}
