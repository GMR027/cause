<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use Cauce\Services\CurrencyService;
use PDO;

/**
 * Registros de la categoría «Compartir» (tabla compartidos).
 */
final class SharingRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function sumPeriod(int $userId, string $inicio, string $fin): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(monto_principal_centavos), 0) FROM compartidos
             WHERE user_id = ? AND fecha BETWEEN ? AND ?'
        );
        $stmt->execute([$userId, $inicio, $fin]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Solo lo que descuenta del % de Compartir (sin extras).
     */
    public function sumPeriodNormal(int $userId, string $inicio, string $fin): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(monto_principal_centavos), 0) FROM compartidos
             WHERE user_id = ? AND fecha BETWEEN ? AND ? AND es_extra = 0'
        );
        $stmt->execute([$userId, $inicio, $fin]);

        return (int) $stmt->fetchColumn();
    }

    public function sumTotal(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(monto_principal_centavos), 0) FROM compartidos WHERE user_id = ?'
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $userId, int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->prepare(
            "SELECT * FROM compartidos WHERE user_id = ?
             ORDER BY fecha DESC, id DESC LIMIT {$limit}"
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /**
     * @return list<array{destinatario: string, total: int}>
     */
    public function byRecipient(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT destinatario, SUM(monto_principal_centavos) AS total
             FROM compartidos
             WHERE user_id = ? AND destinatario IS NOT NULL AND destinatario <> ''
             GROUP BY destinatario
             ORDER BY total DESC"
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function create(
        int $userId,
        int $amount,
        string $currency,
        ?string $recipient,
        bool $extra,
        string $date
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO compartidos
                (user_id, monto_centavos, monto_principal_centavos, moneda, destinatario, es_extra, fecha, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $userId, $amount,
            (new CurrencyService())->toPrincipalForUserId($userId, $amount, $currency, substr($date, 0, 10)),
            $currency, $recipient, $extra ? 1 : 0, $date,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM compartidos WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function update(int $id, int $userId, int $amount, ?string $recipient, bool $extra, string $date): void
    {
        $row = $this->find($id, $userId);
        $currency = (string) ($row['moneda'] ?? 'MXN');
        $stmt = $this->db->prepare(
            'UPDATE compartidos SET monto_centavos = ?, monto_principal_centavos = ?,
                destinatario = ?, es_extra = ?, fecha = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            $amount,
            (new CurrencyService())->toPrincipalForUserId($userId, $amount, $currency, substr($date, 0, 10)),
            $recipient, $extra ? 1 : 0, $date, $id, $userId,
        ]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM compartidos WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }
}
