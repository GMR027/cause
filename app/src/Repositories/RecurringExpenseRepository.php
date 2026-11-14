<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use Cauce\Services\CurrencyService;
use PDO;

final class RecurringExpenseRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allForUser(int $userId, bool $onlyActive = true): array
    {
        $sql = 'SELECT r.*, s.nombre AS subcategoria, d.nombre AS deuda_nombre
                FROM gastos_recurrentes r
                LEFT JOIN subcategorias s ON s.id = r.subcategoria_id
                LEFT JOIN deudas d ON d.id = r.deuda_id
                WHERE r.user_id = ?';
        $params = [$userId];

        if ($onlyActive) {
            $sql .= ' AND r.activo = 1';
        }

        $sql .= ' ORDER BY r.proxima_ejecucion ASC, r.id ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM gastos_recurrentes WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dueForUser(int $userId, string $nowUtc): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM gastos_recurrentes
             WHERE user_id = ? AND activo = 1 AND proxima_ejecucion <= ?
             ORDER BY proxima_ejecucion ASC, id ASC'
        );
        $stmt->execute([$userId, $nowUtc]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lockForUpdate(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM gastos_recurrentes WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function create(
        int $userId,
        string $nombre,
        int $montoCentavos,
        string $moneda,
        bool $variable,
        string $parte,
        ?int $subcategoriaId,
        string $recurrencia,
        string $proximaEjecucionUtc,
        ?int $deudaId = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO gastos_recurrentes
                (user_id, nombre, monto_centavos, monto_principal_centavos, moneda, monto_variable,
                 parte_metodo, subcategoria_id, deuda_id, recurrencia, proxima_ejecucion,
                 activo, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())'
        );

        $stmt->execute([
            $userId,
            $nombre,
            $montoCentavos,
            (new CurrencyService())->toPrincipalForUserId($userId, $montoCentavos, $moneda, gmdate('Y-m-d')),
            $moneda,
            $variable ? 1 : 0,
            $parte,
            $subcategoriaId,
            $deudaId,
            $recurrencia,
            $proximaEjecucionUtc,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(
        int $id,
        int $userId,
        string $nombre,
        int $montoCentavos,
        bool $variable,
        string $parte,
        ?int $subcategoriaId,
        string $recurrencia,
        string $proximaEjecucionUtc
    ): void {
        $existing = $this->findForUser($id, $userId);
        $currency = (string) ($existing['moneda'] ?? 'MXN');
        $stmt = $this->db->prepare(
            'UPDATE gastos_recurrentes SET
                nombre = ?, monto_centavos = ?, monto_principal_centavos = ?, monto_variable = ?,
                parte_metodo = ?, subcategoria_id = ?, recurrencia = ?, proxima_ejecucion = ?,
                updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );

        $stmt->execute([
            mb_substr($nombre, 0, 120),
            $montoCentavos,
            (new CurrencyService())->toPrincipalForUserId($userId, $montoCentavos, $currency, gmdate('Y-m-d')),
            $variable ? 1 : 0,
            $parte,
            $subcategoriaId,
            $recurrencia,
            $proximaEjecucionUtc,
            $id,
            $userId,
        ]);
    }

    public function deactivate(int $id, int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE gastos_recurrentes SET activo = 0, updated_at = NOW() WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
    }

    public function advance(int $id, string $nextUtc): void
    {
        $stmt = $this->db->prepare(
            'UPDATE gastos_recurrentes SET proxima_ejecucion = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$nextUtc, $id]);
    }
}
