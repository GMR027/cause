<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use Cauce\Services\CurrencyService;
use PDO;

final class InvestmentRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function sumContributions(
        int $userId,
        ?string $instrument = null,
        ?string $inicio = null,
        ?string $fin = null,
        ?bool $normalOnly = null,
        int $excludeId = 0
    ): int {
        $where = ['user_id = ?'];
        $params = [$userId];

        if ($instrument !== null) {
            $where[] = 'instrumento = ?';
            $params[] = $instrument;
        }

        if ($inicio !== null && $fin !== null) {
            $where[] = 'fecha BETWEEN ? AND ?';
            $params[] = $inicio;
            $params[] = $fin;
        }

        if ($normalOnly === true) {
            $where[] = 'es_extra = 0';
        }

        if ($excludeId > 0) {
            $where[] = 'id <> ?';
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(monto_principal_centavos), 0) FROM aportaciones_inversion WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function sumWithdrawals(
        int $userId,
        ?string $instrument = null,
        ?string $inicio = null,
        ?string $fin = null,
        int $excludeId = 0
    ): int {
        $where = ['user_id = ?'];
        $params = [$userId];

        if ($instrument !== null) {
            $where[] = 'instrumento = ?';
            $params[] = $instrument;
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
            'SELECT COALESCE(SUM(monto_principal_centavos), 0) FROM retiros_inversion WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string, int>
     */
    public function totalsByInstrument(int $userId): array
    {
        $totals = [];

        $stmt = $this->db->query(
            "SELECT instrumento, SUM(monto_principal_centavos) AS total FROM aportaciones_inversion
             WHERE user_id = {$userId} GROUP BY instrumento"
        );

        foreach ($stmt->fetchAll() as $row) {
            $totals[(string) $row['instrumento']] = (int) $row['total'];
        }

        $stmt = $this->db->query(
            "SELECT instrumento, SUM(monto_principal_centavos) AS total FROM retiros_inversion
             WHERE user_id = {$userId} GROUP BY instrumento"
        );

        foreach ($stmt->fetchAll() as $row) {
            $key = (string) $row['instrumento'];
            $totals[$key] = ($totals[$key] ?? 0) - (int) $row['total'];
        }

        return $totals;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastContribution(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM aportaciones_inversion WHERE user_id = ? ORDER BY fecha DESC, id DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentContributions(int $userId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->query(
            "SELECT * FROM aportaciones_inversion WHERE user_id = {$userId}
             ORDER BY fecha DESC, id DESC LIMIT {$limit}"
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
            "SELECT * FROM retiros_inversion WHERE user_id = {$userId}
             ORDER BY fecha DESC, id DESC LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }

    public function createContribution(
        int $userId,
        int $amount,
        string $currency,
        string $instrument,
        bool $extra,
        string $date
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO aportaciones_inversion
                (user_id, monto_centavos, monto_principal_centavos, moneda, instrumento, es_extra, fecha, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $userId, $amount,
            (new CurrencyService())->toPrincipalForUserId($userId, $amount, $currency, substr($date, 0, 10)),
            $currency, $instrument, $extra ? 1 : 0, $date,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function createWithdrawal(
        int $userId,
        int $amount,
        string $currency,
        string $instrument,
        string $reason,
        ?string $text,
        string $date
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO retiros_inversion
                (user_id, monto_centavos, monto_principal_centavos, moneda, instrumento,
                 motivo_catalogo, motivo_texto, fecha, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $userId, $amount,
            (new CurrencyService())->toPrincipalForUserId($userId, $amount, $currency, substr($date, 0, 10)),
            $currency, $instrument, $reason, $text, $date,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findContribution(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM aportaciones_inversion WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWithdrawal(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM retiros_inversion WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function updateContribution(int $id, int $userId, int $amount, bool $extra, string $date): void
    {
        $stmt = $this->db->prepare(
            'UPDATE aportaciones_inversion SET monto_centavos = ?, es_extra = ?, fecha = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$amount, $extra ? 1 : 0, $date, $id, $userId]);
    }

    public function updateWithdrawal(
        int $id,
        int $userId,
        int $amount,
        string $reason,
        ?string $text,
        string $date
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE retiros_inversion SET monto_centavos = ?, motivo_catalogo = ?, motivo_texto = ?, fecha = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$amount, $reason, $text, $date, $id, $userId]);
    }

    public function deleteContribution(int $id, int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM aportaciones_inversion WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }

    public function deleteWithdrawal(int $id, int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM retiros_inversion WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function contributionsForPeriod(int $userId, string $inicio, string $fin): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM aportaciones_inversion
             WHERE user_id = ? AND fecha BETWEEN ? AND ?
             ORDER BY fecha ASC, id ASC'
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
            'SELECT * FROM retiros_inversion
             WHERE user_id = ? AND fecha BETWEEN ? AND ?
             ORDER BY fecha ASC, id ASC'
        );
        $stmt->execute([$userId, $inicio, $fin]);

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function positionsForUser(int $userId, bool $activeOnly = true): array
    {
        $where = $activeOnly ? 'AND p.activo = 1' : '';
        $stmt = $this->db->prepare(
            "SELECT p.*,
                (SELECT ip.precio FROM inversiones_precios ip
                 WHERE ip.simbolo_proveedor = p.simbolo_proveedor
                 ORDER BY ip.fecha DESC, ip.id DESC LIMIT 1) AS ultimo_precio,
                (SELECT ip.moneda FROM inversiones_precios ip
                 WHERE ip.simbolo_proveedor = p.simbolo_proveedor
                 ORDER BY ip.fecha DESC, ip.id DESC LIMIT 1) AS precio_moneda,
                (SELECT ip.fecha FROM inversiones_precios ip
                 WHERE ip.simbolo_proveedor = p.simbolo_proveedor
                 ORDER BY ip.fecha DESC, ip.id DESC LIMIT 1) AS precio_fecha,
                (SELECT ip.fuente FROM inversiones_precios ip
                 WHERE ip.simbolo_proveedor = p.simbolo_proveedor
                 ORDER BY ip.fecha DESC, ip.id DESC LIMIT 1) AS precio_fuente
             FROM inversiones_posiciones p
             WHERE p.user_id = ? {$where}
             ORDER BY p.created_at ASC, p.id ASC"
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPosition(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM inversiones_posiciones WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function createPosition(
        int $userId,
        string $type,
        string $market,
        ?string $ticker,
        ?string $providerSymbol,
        string $name,
        string $currency,
        float $quantity,
        int $costCents,
        ?int $costPrincipalCents,
        string $valueMode,
        ?int $manualValueCents,
        ?int $manualValuePrincipalCents,
        ?string $manualValueDate
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO inversiones_posiciones
                (user_id, tipo, mercado, ticker, simbolo_proveedor, nombre, moneda, cantidad,
                 costo_total_centavos, costo_total_principal_centavos, modo_valor,
                 valor_manual_centavos, valor_manual_principal_centavos, fecha_valor_manual,
                 activo, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())'
        );
        $stmt->execute([
            $userId, $type, $market, $ticker, $providerSymbol, $name, $currency, $quantity,
            $costCents, $costPrincipalCents, $valueMode,
            $manualValueCents, $manualValuePrincipalCents, $manualValueDate,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updatePosition(
        int $id,
        int $userId,
        string $type,
        string $market,
        ?string $ticker,
        ?string $providerSymbol,
        string $name,
        string $currency,
        float $quantity,
        int $costCents,
        ?int $costPrincipalCents,
        string $valueMode,
        ?int $manualValueCents,
        ?int $manualValuePrincipalCents,
        ?string $manualValueDate
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE inversiones_posiciones SET
                tipo = ?, mercado = ?, ticker = ?, simbolo_proveedor = ?, nombre = ?, moneda = ?,
                cantidad = ?, costo_total_centavos = ?, costo_total_principal_centavos = ?,
                modo_valor = ?, valor_manual_centavos = ?, valor_manual_principal_centavos = ?,
                fecha_valor_manual = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            $type, $market, $ticker, $providerSymbol, $name, $currency, $quantity,
            $costCents, $costPrincipalCents, $valueMode,
            $manualValueCents, $manualValuePrincipalCents, $manualValueDate,
            $id, $userId,
        ]);
    }

    public function updatePositionValue(
        int $id,
        int $userId,
        int $manualValueCents,
        ?int $manualValuePrincipalCents,
        string $date
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE inversiones_posiciones SET
                valor_manual_centavos = ?, valor_manual_principal_centavos = ?,
                fecha_valor_manual = ?, modo_valor = "manual", updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$manualValueCents, $manualValuePrincipalCents, $date, $id, $userId]);
    }

    public function deletePosition(int $id, int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM inversiones_posiciones WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }

    public function savePrice(
        string $providerSymbol,
        string $market,
        string $currency,
        float $price,
        string $date,
        string $source
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO inversiones_precios
                (simbolo_proveedor, mercado, moneda, precio, fecha, fuente, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                mercado = VALUES(mercado), moneda = VALUES(moneda), precio = VALUES(precio),
                fuente = VALUES(fuente)'
        );
        $stmt->execute([$providerSymbol, $market, $currency, $price, $date, $source]);
    }
}
