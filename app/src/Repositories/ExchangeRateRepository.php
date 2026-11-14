<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

/**
 * Tasas de cambio globales administradas por el admin.
 */
final class ExchangeRateRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $stmt = $this->db->query(
            'SELECT * FROM tasas_cambio ORDER BY fecha DESC, moneda_origen ASC, moneda_destino ASC, id DESC'
        );

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tasas_cambio WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forPairOnDate(string $from, string $to, string $date): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM tasas_cambio
             WHERE moneda_origen = ? AND moneda_destino = ? AND fecha = ?
             LIMIT 1'
        );
        $stmt->execute([$from, $to, $date]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestForPair(string $from, string $to, string $date): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM tasas_cambio
             WHERE moneda_origen = ? AND moneda_destino = ? AND fecha <= ?
             ORDER BY fecha DESC, id DESC LIMIT 1'
        );
        $stmt->execute([$from, $to, $date]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function exists(string $from, string $to, string $date, int $excludeId = 0): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM tasas_cambio
             WHERE moneda_origen = ? AND moneda_destino = ? AND fecha = ? AND id <> ?
             LIMIT 1'
        );
        $stmt->execute([$from, $to, $date, $excludeId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array{moneda_origen: string, moneda_destino: string, tasa: float, fecha: string} $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tasas_cambio
                (moneda_origen, moneda_destino, tasa, fecha, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['moneda_origen'],
            $data['moneda_destino'],
            $data['tasa'],
            $data['fecha'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array{moneda_origen: string, moneda_destino: string, tasa: float, fecha: string} $data
     */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE tasas_cambio SET
                moneda_origen = ?, moneda_destino = ?, tasa = ?, fecha = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $data['moneda_origen'],
            $data['moneda_destino'],
            $data['tasa'],
            $data['fecha'],
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM tasas_cambio WHERE id = ?')->execute([$id]);
    }

    public function latestAny(): ?array
    {
        $stmt = $this->db->query(
            'SELECT * FROM tasas_cambio ORDER BY fecha DESC, id DESC LIMIT 1'
        );
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }
}
