<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use Cauce\Services\CurrencyService;
use PDO;

/**
 * Persistencia de deudas y pagos.
 */
final class DebtRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allForUser(int $userId, bool $onlyActive = false): array
    {
        $sql = 'SELECT * FROM deudas WHERE user_id = ?';

        if ($onlyActive) {
            $sql .= ' AND activa = 1';
        }

        $sql .= ' ORDER BY activa DESC, fecha_limite ASC, created_at ASC, id ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM deudas WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array{user_id: int, nombre: string, monto_total_centavos: int, saldo_actual_centavos: int, moneda: string, tasa_interes: ?float, pago_minimo_centavos: ?int, fecha_limite: ?string} $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO deudas
                (user_id, nombre, monto_total_centavos, saldo_actual_centavos,
                 monto_total_principal_centavos, saldo_actual_principal_centavos, moneda,
                 tasa_interes, pago_minimo_centavos, pago_minimo_principal_centavos,
                 fecha_limite, activa, created_at, updated_at)
             VALUES
                (:user_id, :nombre, :total, :saldo, :total_principal, :saldo_principal, :moneda,
                 :tasa, :minimo, :minimo_principal, :limite, :activa, NOW(), NOW())'
        );
        $currency = (string) $data['moneda'];
        $today = gmdate('Y-m-d');
        $converter = new CurrencyService();
        $minimum = $data['pago_minimo_centavos'];
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':nombre' => $data['nombre'],
            ':total' => $data['monto_total_centavos'],
            ':saldo' => $data['saldo_actual_centavos'],
            ':total_principal' => $converter->toPrincipalForUserId((int) $data['user_id'], (int) $data['monto_total_centavos'], $currency, $today),
            ':saldo_principal' => $converter->toPrincipalForUserId((int) $data['user_id'], (int) $data['saldo_actual_centavos'], $currency, $today),
            ':moneda' => $data['moneda'],
            ':tasa' => $data['tasa_interes'],
            ':minimo' => $minimum,
            ':minimo_principal' => $minimum !== null
                ? $converter->toPrincipalForUserId((int) $data['user_id'], (int) $minimum, $currency, $today)
                : null,
            ':limite' => $data['fecha_limite'],
            ':activa' => ($data['activa'] ?? ((int) $data['saldo_actual_centavos'] > 0)) ? 1 : 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array{nombre: string, monto_total_centavos: int, saldo_actual_centavos: int, tasa_interes: ?float, pago_minimo_centavos: ?int, fecha_limite: ?string, activa: bool} $data
     */
    public function update(int $id, int $userId, array $data): void
    {
        $currency = (string) ($data['moneda'] ?? 'MXN');
        $today = gmdate('Y-m-d');
        $converter = new CurrencyService();
        $minimum = $data['pago_minimo_centavos'];
        $stmt = $this->db->prepare(
            'UPDATE deudas SET
                nombre = ?, moneda = ?, monto_total_centavos = ?, saldo_actual_centavos = ?,
                monto_total_principal_centavos = ?, saldo_actual_principal_centavos = ?,
                tasa_interes = ?, pago_minimo_centavos = ?, pago_minimo_principal_centavos = ?,
                fecha_limite = ?, activa = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            $data['nombre'],
            $currency,
            $data['monto_total_centavos'],
            $data['saldo_actual_centavos'],
            $converter->toPrincipalForUserId($userId, (int) $data['monto_total_centavos'], $currency, $today),
            $converter->toPrincipalForUserId($userId, (int) $data['saldo_actual_centavos'], $currency, $today),
            $data['tasa_interes'],
            $minimum,
            $minimum !== null ? $converter->toPrincipalForUserId($userId, (int) $minimum, $currency, $today) : null,
            $data['fecha_limite'],
            $data['activa'] ? 1 : 0,
            $id,
            $userId,
        ]);
    }

    public function delete(int $id, int $userId): void
    {
        $this->db->prepare('DELETE FROM deudas WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
    }

    public function applyExcess(int $id, int $userId, int $excess): void
    {
        $debt = $this->findForUser($id, $userId);
        $currency = (string) ($debt['moneda'] ?? 'MXN');
        $user = (new \Cauce\Repositories\UserRepository())->findById($userId);
        $original = $excess;

        if ($user !== null && $currency !== (string) ($user['moneda_activa'] ?? 'MXN')) {
            try {
                $original = (new CurrencyService())->convert(
                    $user,
                    $excess,
                    (string) ($user['moneda_activa'] ?? 'MXN'),
                    $currency,
                    gmdate('Y-m-d')
                )['centavos'];
            } catch (\RuntimeException $exception) {
                throw $exception;
            }
        }

        $stmt = $this->db->prepare(
            'UPDATE deudas SET
                monto_total_centavos = monto_total_centavos + ?,
                saldo_actual_centavos = saldo_actual_centavos + ?,
                monto_total_principal_centavos = COALESCE(monto_total_principal_centavos, 0) + ?,
                saldo_actual_principal_centavos = COALESCE(saldo_actual_principal_centavos, 0) + ?,
                activa = 1, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$original, $original, $excess, $excess, $id, $userId]);
    }

    public function reduceBalance(int $id, int $userId, int $amount, ?int $principalAmount = null): void
    {
        $principalAmount ??= $amount;
        $stmt = $this->db->prepare(
            'UPDATE deudas SET
                activa = CASE WHEN saldo_actual_centavos <= ? THEN 0 ELSE 1 END,
                saldo_actual_centavos = GREATEST(0, saldo_actual_centavos - ?),
                saldo_actual_principal_centavos = GREATEST(0, COALESCE(saldo_actual_principal_centavos, 0) - ?),
                updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$amount, $amount, $principalAmount, $id, $userId]);
    }

    /**
     * Deshace un pago: regresa el monto al saldo, reactiva la deuda y borra
     * el registro del pago.
     *
     * @param array<string, mixed> $payment fila de pagos_deuda
     */
    public function revertPayment(array $payment): void
    {
        $amount = (int) $payment['monto_centavos'];
        $principal = (int) ($payment['monto_principal_centavos'] ?? $amount);
        $stmt = $this->db->prepare(
            'UPDATE deudas SET
                saldo_actual_centavos = LEAST(monto_total_centavos, saldo_actual_centavos + ?),
                saldo_actual_principal_centavos = COALESCE(saldo_actual_principal_centavos, 0) + ?,
                activa = 1, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$amount, $principal, (int) $payment['deuda_id'], (int) $payment['user_id']]);

        $stmt = $this->db->prepare('DELETE FROM pagos_deuda WHERE id = ? AND user_id = ?');
        $stmt->execute([(int) $payment['id'], (int) $payment['user_id']]);
    }

    /**
     * Resta a una deuda el exceso que le mandó un gasto. Si la deuda nació de
     * ese exceso y queda en cero sin pagos, se elimina.
     */
    public function revertExcess(int $id, int $userId, int $principalExcess): void
    {
        $debt = $this->findForUser($id, $userId);

        if ($debt === null || $principalExcess <= 0) {
            return;
        }

        $user = (new \Cauce\Repositories\UserRepository())->findById($userId);
        $currency = (string) ($debt['moneda'] ?? 'MXN');
        $original = $principalExcess;

        if ($user !== null && $currency !== (string) ($user['moneda_activa'] ?? 'MXN')) {
            try {
                $original = (new CurrencyService())->convert(
                    $user,
                    $principalExcess,
                    (string) ($user['moneda_activa'] ?? 'MXN'),
                    $currency,
                    gmdate('Y-m-d')
                )['centavos'];
            } catch (\RuntimeException) {
                $original = $principalExcess;
            }
        }

        $stmt = $this->db->prepare(
            'UPDATE deudas SET
                monto_total_centavos = GREATEST(0, monto_total_centavos - ?),
                saldo_actual_centavos = GREATEST(0, saldo_actual_centavos - ?),
                monto_total_principal_centavos = GREATEST(0, COALESCE(monto_total_principal_centavos, 0) - ?),
                saldo_actual_principal_centavos = GREATEST(0, COALESCE(saldo_actual_principal_centavos, 0) - ?),
                -- MySQL evalúa las asignaciones en orden: aquí el saldo ya está actualizado.
                activa = CASE WHEN saldo_actual_centavos > 0 THEN 1 ELSE 0 END,
                updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$original, $original, $principalExcess, $principalExcess, $id, $userId]);

        $after = $this->findForUser($id, $userId);

        if ($after !== null && (int) $after['monto_total_centavos'] <= 0 && $this->paymentsForDebt($id, $userId) === []) {
            $this->delete($id, $userId);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function paymentsForGasto(int $gastoId, int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM pagos_deuda WHERE gasto_id = ? AND user_id = ?');
        $stmt->execute([$gastoId, $userId]);

        return $stmt->fetchAll();
    }

    /**
     * Pagos del período que no registraron gasto (se pagaron directo).
     *
     * @return list<array<string, mixed>>
     */
    public function paymentsWithoutExpense(int $userId, string $inicio, string $fin): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM pagos_deuda WHERE user_id = ? AND gasto_id IS NULL AND fecha BETWEEN ? AND ?'
        );
        $stmt->execute([$userId, $inicio, $fin]);

        return $stmt->fetchAll();
    }

    public function sumActiveBalance(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(saldo_actual_principal_centavos), 0) FROM deudas WHERE user_id = ? AND activa = 1'
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    public function sumOriginal(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(monto_total_principal_centavos), 0) FROM deudas WHERE user_id = ?'
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array{deuda_id: int, user_id: int, monto_centavos: int, moneda: string, fecha: string, gasto_id: ?int, nota: ?string} $data
     */
    public function createPayment(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pagos_deuda
                (deuda_id, user_id, monto_centavos, monto_principal_centavos, moneda,
                 fecha, gasto_id, nota, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['deuda_id'],
            $data['user_id'],
            $data['monto_centavos'],
            (new CurrencyService())->toPrincipalForUserId(
                (int) $data['user_id'],
                (int) $data['monto_centavos'],
                (string) $data['moneda'],
                substr((string) $data['fecha'], 0, 10)
            ),
            $data['moneda'],
            $data['fecha'],
            $data['gasto_id'],
            $data['nota'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function paymentsForUser(int $userId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->db->prepare(
            "SELECT p.*, d.nombre AS deuda_nombre, d.moneda AS deuda_moneda
             FROM pagos_deuda p
             JOIN deudas d ON d.id = p.deuda_id
             WHERE p.user_id = ?
             ORDER BY p.fecha DESC, p.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function paymentsForDebt(int $debtId, int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM pagos_deuda WHERE deuda_id = ? AND user_id = ? ORDER BY fecha DESC, id DESC'
        );
        $stmt->execute([$debtId, $userId]);

        return $stmt->fetchAll();
    }

    public function countActive(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM deudas WHERE user_id = ? AND activa = 1');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function reminderForDebt(int $debtId, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM gastos_recurrentes
             WHERE deuda_id = ? AND user_id = ? AND activo = 1
             ORDER BY proxima_ejecucion ASC, id ASC LIMIT 1'
        );
        $stmt->execute([$debtId, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }
}
