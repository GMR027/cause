<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use Cauce\Services\CurrencyService;
use PDO;

final class SavingsRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function metasForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, c.nombre AS cuenta_nombre, c.moneda AS cuenta_moneda
             FROM metas_ahorro m
             LEFT JOIN cuentas_ahorro c ON c.id = m.cuenta_id
             WHERE m.user_id = ?
             ORDER BY m.es_emergencia DESC, m.created_at ASC, m.id ASC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function accountsForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*,
                COALESCE((SELECT SUM(a.monto_centavos) FROM aportaciones_ahorro a WHERE a.cuenta_id = c.id), 0) AS aportado_centavos,
                COALESCE((SELECT SUM(r.monto_centavos) FROM retiros_ahorro r WHERE r.cuenta_id = c.id), 0) AS retirado_centavos,
                COALESCE((SELECT SUM(t.monto_centavos) FROM transferencias_ahorro t WHERE t.cuenta_origen_id = c.id), 0) AS transferido_salida_centavos,
                COALESCE((SELECT SUM(t.monto_centavos) FROM transferencias_ahorro t WHERE t.cuenta_destino_id = c.id), 0) AS transferido_entrada_centavos,
                COALESCE((SELECT SUM(COALESCE(a.monto_principal_centavos, a.monto_centavos)) FROM aportaciones_ahorro a WHERE a.cuenta_id = c.id), 0) AS aportado_principal_centavos,
                COALESCE((SELECT SUM(COALESCE(r.monto_principal_centavos, r.monto_centavos)) FROM retiros_ahorro r WHERE r.cuenta_id = c.id), 0) AS retirado_principal_centavos,
                COALESCE((SELECT SUM(COALESCE(t.monto_principal_centavos, t.monto_centavos)) FROM transferencias_ahorro t WHERE t.cuenta_origen_id = c.id), 0) AS transferido_salida_principal_centavos,
                COALESCE((SELECT SUM(COALESCE(t.monto_principal_centavos, t.monto_centavos)) FROM transferencias_ahorro t WHERE t.cuenta_destino_id = c.id), 0) AS transferido_entrada_principal_centavos
             FROM cuentas_ahorro c
             WHERE c.user_id = ?
             ORDER BY c.es_predeterminada DESC, c.activa DESC, c.created_at ASC, c.id ASC"
        );
        $stmt->execute([$userId]);

        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['saldo_centavos'] = (int) $row['aportado_centavos']
                - (int) $row['retirado_centavos']
                + (int) $row['transferido_entrada_centavos']
                - (int) $row['transferido_salida_centavos'];
            $row['saldo_principal_centavos'] = (int) $row['aportado_principal_centavos']
                - (int) $row['retirado_principal_centavos']
                + (int) $row['transferido_entrada_principal_centavos']
                - (int) $row['transferido_salida_principal_centavos'];
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAccount(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM cuentas_ahorro WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function defaultAccount(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM cuentas_ahorro
             WHERE user_id = ? AND es_predeterminada = 1
             ORDER BY activa DESC, id ASC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function ensureDefaultAccount(int $userId, string $currency): array
    {
        $existing = $this->defaultAccount($userId);

        if ($existing !== null) {
            return $existing;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO cuentas_ahorro
                (user_id, nombre, tipo, moneda, es_predeterminada, activa, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, 1, NOW(), NOW())'
        );
        $stmt->execute([$userId, 'Ahorro general', 'otro', $currency]);

        return $this->findAccount((int) $this->db->lastInsertId(), $userId) ?? [];
    }

    public function createAccount(
        int $userId,
        string $name,
        string $type,
        ?string $institution,
        string $currency,
        bool $default
    ): int {
        if ($default) {
            $this->clearDefault($userId);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO cuentas_ahorro
                (user_id, nombre, tipo, institucion, moneda, es_predeterminada, activa, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())'
        );
        $stmt->execute([$userId, $name, $type, $institution, $currency, $default ? 1 : 0]);

        return (int) $this->db->lastInsertId();
    }

    public function updateAccount(
        int $id,
        int $userId,
        string $name,
        string $type,
        ?string $institution,
        bool $active,
        bool $default
    ): void {
        if ($default) {
            $this->clearDefault($userId);
        }

        $stmt = $this->db->prepare(
            'UPDATE cuentas_ahorro
             SET nombre = ?, tipo = ?, institucion = ?, activa = ?, es_predeterminada = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$name, $type, $institution, $active ? 1 : 0, $default ? 1 : 0, $id, $userId]);
    }

    public function setDefault(int $id, int $userId): void
    {
        $this->clearDefault($userId);
        $stmt = $this->db->prepare(
            'UPDATE cuentas_ahorro SET es_predeterminada = 1, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
    }

    private function clearDefault(int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE cuentas_ahorro SET es_predeterminada = 0, updated_at = NOW() WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
    }

    public function accountHasMovements(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT (
                (SELECT COUNT(*) FROM aportaciones_ahorro WHERE cuenta_id = ? AND user_id = ?)
                + (SELECT COUNT(*) FROM retiros_ahorro WHERE cuenta_id = ? AND user_id = ?)
                + (SELECT COUNT(*) FROM transferencias_ahorro WHERE user_id = ? AND (cuenta_origen_id = ? OR cuenta_destino_id = ?))
             ) AS total'
        );
        $stmt->execute([$id, $userId, $id, $userId, $userId, $id, $id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function createTransfer(
        int $userId,
        int $originId,
        int $destinationId,
        ?int $metaId,
        string $reason,
        int $amount,
        string $currency,
        string $date,
        ?string $note
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO transferencias_ahorro
                (user_id, cuenta_origen_id, cuenta_destino_id, meta_id, motivo, monto_centavos,
                 moneda, monto_principal_centavos, nota, fecha, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $userId, $originId, $destinationId, $metaId, $reason, $amount,
            $currency,
            $this->principalCents($userId, $amount, $currency, substr($date, 0, 10)),
            $note, $date,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findMeta(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM metas_ahorro WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function ensureEmergencyMeta(int $userId, int $objective, string $currency, ?int $accountId = null): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM metas_ahorro WHERE user_id = ? AND es_emergencia = 1 LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if (is_array($row)) {
            return $row;
        }

        $insert = $this->db->prepare(
            'INSERT INTO metas_ahorro
                (user_id, cuenta_id, nombre, tipo, objetivo_centavos, objetivo_principal_centavos, moneda,
                 es_emergencia, meses_objetivo, activa, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 3, 1, NOW(), NOW())'
        );
        $insert->execute([
            $userId, $accountId, 'Fondo de emergencia', 'ahorro', $objective,
            $this->principalCents($userId, $objective, $currency, gmdate('Y-m-d')),
            $currency,
        ]);

        return $this->findMeta((int) $this->db->lastInsertId(), $userId) ?? [];
    }

    public function createMeta(
        int $userId,
        ?int $accountId,
        string $name,
        string $type,
        int $objective,
        string $currency,
        ?string $targetDate,
        ?int $months
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO metas_ahorro
                (user_id, cuenta_id, nombre, tipo, objetivo_centavos, objetivo_principal_centavos, moneda,
                 fecha_objetivo, plazo_meses, es_emergencia, meses_objetivo, activa, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 3, 1, NOW(), NOW())'
        );
        $stmt->execute([
            $userId, $accountId, $name, $type, $objective,
            $this->principalCents($userId, $objective, $currency, gmdate('Y-m-d')),
            $currency, $targetDate, $months,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateMeta(
        int $id,
        int $userId,
        string $name,
        string $type,
        int $objective,
        ?string $targetDate,
        ?int $months,
        bool $active
    ): void {
        $meta = $this->findMeta($id, $userId);
        $currency = (string) ($meta['moneda'] ?? 'MXN');
        $stmt = $this->db->prepare(
            'UPDATE metas_ahorro SET
                nombre = ?, tipo = ?, objetivo_centavos = ?, objetivo_principal_centavos = ?,
                fecha_objetivo = ?, plazo_meses = ?, activa = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            $name, $type, $objective,
            $this->principalCents($userId, $objective, $currency, gmdate('Y-m-d')),
            $targetDate, $months, $active ? 1 : 0, $id, $userId,
        ]);
    }

    public function updateEmergencyObjective(int $id, int $userId, int $objective): void
    {
        $meta = $this->findMeta($id, $userId);
        $currency = (string) ($meta['moneda'] ?? 'MXN');
        $stmt = $this->db->prepare(
            'UPDATE metas_ahorro SET objetivo_centavos = ?, objetivo_principal_centavos = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ? AND es_emergencia = 1'
        );
        $stmt->execute([
            $objective,
            $this->principalCents($userId, $objective, $currency, gmdate('Y-m-d')),
            $id, $userId,
        ]);
    }

    public function updateMetaAccount(int $id, int $userId, int $accountId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE metas_ahorro SET cuenta_id = ?, updated_at = NOW() WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$accountId, $id, $userId]);
    }

    public function deactivateMeta(int $id, int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE metas_ahorro SET activa = 0, updated_at = NOW() WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
    }

    public function sumContributions(
        int $userId,
        ?int $metaId = null,
        ?string $inicio = null,
        ?string $fin = null,
        int $excludeId = 0
    ): int {
        $where = ['user_id = ?'];
        $params = [$userId];

        if ($metaId === null) {
            $where[] = 'meta_id IS NULL';
        } else {
            $where[] = 'meta_id = ?';
            $params[] = $metaId;
        }

        if ($inicio !== null && $fin !== null) {
            $where[] = 'fecha BETWEEN ? AND ?';
            $params[] = $inicio;
            $params[] = $fin;
        }

        if ($excludeId > 0) {
            $where[] = 'id <> ?';
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(monto_principal_centavos), 0) FROM aportaciones_ahorro WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function sumWithdrawals(
        int $userId,
        ?int $metaId = null,
        ?string $inicio = null,
        ?string $fin = null,
        int $excludeId = 0
    ): int {
        $where = ['user_id = ?'];
        $params = [$userId];

        if ($metaId === null) {
            $where[] = 'meta_id IS NULL';
        } else {
            $where[] = 'meta_id = ?';
            $params[] = $metaId;
        }

        if ($inicio !== null && $fin !== null) {
            $where[] = 'fecha BETWEEN ? AND ?';
            $params[] = $inicio;
            $params[] = $fin;
        }

        if ($excludeId > 0) {
            $where[] = 'id <> ?';
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(monto_principal_centavos), 0) FROM retiros_ahorro WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function sumAllContributions(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(monto_principal_centavos), 0) FROM aportaciones_ahorro WHERE user_id = ?'
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    public function sumPeriodContributions(int $userId, string $inicio, string $fin): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(monto_principal_centavos), 0) FROM aportaciones_ahorro
             WHERE user_id = ? AND fecha BETWEEN ? AND ?
               AND es_saldo_inicial = 0 AND es_reasignacion = 0'
        );
        $stmt->execute([$userId, $inicio, $fin]);

        return (int) $stmt->fetchColumn();
    }

    public function sumPeriodWithdrawals(int $userId, string $inicio, string $fin): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(monto_principal_centavos), 0) FROM retiros_ahorro
             WHERE user_id = ? AND fecha BETWEEN ? AND ? AND es_reasignacion = 0'
        );
        $stmt->execute([$userId, $inicio, $fin]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function contributionsForPeriod(int $userId, string $inicio, string $fin): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, m.nombre AS meta_nombre, c.nombre AS cuenta_nombre
             FROM aportaciones_ahorro a
             LEFT JOIN metas_ahorro m ON m.id = a.meta_id
             LEFT JOIN cuentas_ahorro c ON c.id = a.cuenta_id
             WHERE a.user_id = ? AND a.fecha BETWEEN ? AND ?
               AND a.es_saldo_inicial = 0 AND a.es_reasignacion = 0
             ORDER BY a.fecha ASC, a.id ASC'
        );
        $stmt->execute([$userId, $inicio, $fin]);

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function withdrawalsForPeriod(int $userId, string $inicio, string $fin): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, m.nombre AS meta_nombre, c.nombre AS cuenta_nombre
             FROM retiros_ahorro r
             LEFT JOIN metas_ahorro m ON m.id = r.meta_id
             LEFT JOIN cuentas_ahorro c ON c.id = r.cuenta_id
             WHERE r.user_id = ? AND r.fecha BETWEEN ? AND ? AND r.es_reasignacion = 0
             ORDER BY r.fecha ASC, r.id ASC'
        );
        $stmt->execute([$userId, $inicio, $fin]);

        return $stmt->fetchAll();
    }

    public function sumAllWithdrawals(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(monto_principal_centavos), 0) FROM retiros_ahorro WHERE user_id = ?'
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentContributions(int $userId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->query(
            "SELECT a.*, m.nombre AS meta_nombre, c.nombre AS cuenta_nombre
             FROM aportaciones_ahorro a
             LEFT JOIN metas_ahorro m ON m.id = a.meta_id
             LEFT JOIN cuentas_ahorro c ON c.id = a.cuenta_id
             WHERE a.user_id = {$userId}
               AND a.es_reasignacion = 0
             ORDER BY a.fecha DESC, a.id DESC LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentWithdrawals(int $userId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->query(
            "SELECT r.*, m.nombre AS meta_nombre, c.nombre AS cuenta_nombre
             FROM retiros_ahorro r
             LEFT JOIN metas_ahorro m ON m.id = r.meta_id
             LEFT JOIN cuentas_ahorro c ON c.id = r.cuenta_id
             WHERE r.user_id = {$userId}
               AND r.es_reasignacion = 0
             ORDER BY r.fecha DESC, r.id DESC LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentTransfers(int $userId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->query(
            "SELECT t.*, co.nombre AS origen_nombre, cd.nombre AS destino_nombre, m.nombre AS meta_nombre
             FROM transferencias_ahorro t
             JOIN cuentas_ahorro co ON co.id = t.cuenta_origen_id
             JOIN cuentas_ahorro cd ON cd.id = t.cuenta_destino_id
             LEFT JOIN metas_ahorro m ON m.id = t.meta_id
             WHERE t.user_id = {$userId}
             ORDER BY t.fecha DESC, t.id DESC LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }

    public function createContribution(
        int $userId,
        ?int $accountId,
        ?int $metaId,
        int $amount,
        string $currency,
        string $date,
        bool $initial = false,
        bool $reallocation = false
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO aportaciones_ahorro
                (user_id, cuenta_id, meta_id, monto_centavos, monto_principal_centavos, moneda,
                 es_saldo_inicial, es_reasignacion, fecha, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $userId, $accountId, $metaId, $amount,
            $this->principalCents($userId, $amount, $currency, substr($date, 0, 10)),
            $currency, $initial ? 1 : 0, $reallocation ? 1 : 0, $date,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function createWithdrawal(
        int $userId,
        ?int $accountId,
        ?int $metaId,
        int $amount,
        string $currency,
        string $reason,
        ?string $text,
        string $date,
        bool $reallocation = false
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO retiros_ahorro
                (user_id, cuenta_id, meta_id, monto_centavos, monto_principal_centavos, moneda,
                 es_reasignacion, motivo_catalogo, motivo_texto, fecha, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $userId, $accountId, $metaId, $amount,
            $this->principalCents($userId, $amount, $currency, substr($date, 0, 10)),
            $currency, $reallocation ? 1 : 0, $reason, $text, $date,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findContribution(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM aportaciones_ahorro WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWithdrawal(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM retiros_ahorro WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function updateContribution(int $id, int $userId, int $amount, string $date): void
    {
        $row = $this->findContribution($id, $userId);
        $currency = (string) ($row['moneda'] ?? 'MXN');
        $stmt = $this->db->prepare(
            'UPDATE aportaciones_ahorro SET monto_centavos = ?, monto_principal_centavos = ?,
                fecha = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            $amount,
            $this->principalCents($userId, $amount, $currency, substr($date, 0, 10)),
            $date, $id, $userId,
        ]);
    }

    public function updateWithdrawal(
        int $id,
        int $userId,
        int $amount,
        string $reason,
        ?string $text,
        string $date
    ): void {
        $row = $this->findWithdrawal($id, $userId);
        $currency = (string) ($row['moneda'] ?? 'MXN');
        $stmt = $this->db->prepare(
            'UPDATE retiros_ahorro SET monto_centavos = ?, monto_principal_centavos = ?,
                motivo_catalogo = ?, motivo_texto = ?, fecha = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            $amount,
            $this->principalCents($userId, $amount, $currency, substr($date, 0, 10)),
            $reason, $text, $date, $id, $userId,
        ]);
    }

    public function deleteContribution(int $id, int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM aportaciones_ahorro WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }

    public function linkLastWithdrawalToGasto(int $userId, int $metaId, int $gastoId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE retiros_ahorro SET gasto_asociado_id = ?
             WHERE user_id = ? AND meta_id = ? AND gasto_asociado_id IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$gastoId, $userId, $metaId]);
    }

    public function deleteWithdrawal(int $id, int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM retiros_ahorro WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }

    /**
     * @return array<int, array{aportado: int, retirado: int}>
     */
    public function totalsByMeta(int $userId): array
    {
        $totals = [];

        $stmt = $this->db->query(
            "SELECT meta_id, SUM(monto_principal_centavos) AS total FROM aportaciones_ahorro
             WHERE user_id = {$userId} GROUP BY meta_id"
        );

        foreach ($stmt->fetchAll() as $row) {
            $key = $row['meta_id'] === null ? 0 : (int) $row['meta_id'];
            $totals[$key]['aportado'] = (int) $row['total'];
            $totals[$key]['retirado'] = $totals[$key]['retirado'] ?? 0;
        }

        $stmt = $this->db->query(
            "SELECT meta_id, SUM(monto_principal_centavos) AS total FROM retiros_ahorro
             WHERE user_id = {$userId} GROUP BY meta_id"
        );

        foreach ($stmt->fetchAll() as $row) {
            $key = $row['meta_id'] === null ? 0 : (int) $row['meta_id'];
            $totals[$key]['retirado'] = (int) $row['total'];
            $totals[$key]['aportado'] = $totals[$key]['aportado'] ?? 0;
        }

        return $totals;
    }

    private function principalCents(int $userId, int $amount, string $currency, string $date): ?int
    {
        return (new CurrencyService())->toPrincipalForUserId($userId, $amount, $currency, $date);
    }
}
