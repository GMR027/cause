<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use Cauce\Services\CurrencyService;
use PDO;

final class DonationRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function sumPeriod(int $userId, string $inicio, string $fin): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(monto_principal_centavos), 0) FROM donaciones
             WHERE user_id = ? AND fecha BETWEEN ? AND ?'
        );
        $stmt->execute([$userId, $inicio, $fin]);

        return (int) $stmt->fetchColumn();
    }

    public function sumTotal(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(monto_principal_centavos), 0) FROM donaciones WHERE user_id = ?'
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    public function sumPeriodNormal(int $userId, string $inicio, string $fin, int $excludeId = 0): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(monto_principal_centavos), 0) FROM donaciones
             WHERE user_id = ? AND fecha BETWEEN ? AND ? AND es_extra = 0 AND id <> ?'
        );
        $stmt->execute([$userId, $inicio, $fin, $excludeId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forPeriod(int $userId, string $inicio, string $fin): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM donaciones WHERE user_id = ? AND fecha BETWEEN ? AND ?
             ORDER BY fecha DESC, id DESC'
        );
        $stmt->execute([$userId, $inicio, $fin]);

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $userId, int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->query(
            "SELECT * FROM donaciones WHERE user_id = {$userId}
             ORDER BY fecha DESC, id DESC LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }

    /**
     * @return list<array{causa: string, total: int}>
     */
    public function byCause(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT causa, SUM(monto_principal_centavos) AS total
             FROM donaciones
             WHERE user_id = ? AND causa IS NOT NULL AND causa <> ''
             GROUP BY causa
             ORDER BY total DESC"
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function create(
        int $userId,
        int $amount,
        string $currency,
        ?string $cause,
        bool $extra,
        string $date
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO donaciones
                (user_id, monto_centavos, monto_principal_centavos, moneda, causa, es_extra, fecha, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $userId, $amount,
            (new CurrencyService())->toPrincipalForUserId($userId, $amount, $currency, substr($date, 0, 10)),
            $currency, $cause, $extra ? 1 : 0, $date,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM donaciones WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function update(int $id, int $userId, int $amount, ?string $cause, bool $extra, string $date): void
    {
        $row = $this->find($id, $userId);
        $currency = (string) ($row['moneda'] ?? 'MXN');
        $stmt = $this->db->prepare(
            'UPDATE donaciones SET monto_centavos = ?, monto_principal_centavos = ?,
                causa = ?, es_extra = ?, fecha = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            $amount,
            (new CurrencyService())->toPrincipalForUserId($userId, $amount, $currency, substr($date, 0, 10)),
            $cause, $extra ? 1 : 0, $date, $id, $userId,
        ]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM donaciones WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }
}
