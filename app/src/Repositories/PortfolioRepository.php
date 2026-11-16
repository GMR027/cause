<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

/**
 * Portafolios de inversión y sus rendimientos (intereses, dividendos, bajas…).
 */
final class PortfolioRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function connection(): PDO
    {
        return $this->db;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM inversiones_portafolios WHERE user_id = ? ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function count(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM inversiones_portafolios WHERE user_id = ?');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM inversiones_portafolios WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByName(int $userId, string $name): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM inversiones_portafolios WHERE user_id = ? AND nombre = ? LIMIT 1');
        $stmt->execute([$userId, $name]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function create(int $userId, string $name, string $class, ?string $institution, ?string $legacy): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO inversiones_portafolios (user_id, nombre, clase, institucion, instrumento_legado, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$userId, $name, $class, $institution, $legacy]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $userId, string $name, string $class, ?string $institution): void
    {
        $stmt = $this->db->prepare(
            'UPDATE inversiones_portafolios SET nombre = ?, clase = ?, institucion = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$name, $class, $institution, $id, $userId]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM inversiones_portafolios WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }

    /**
     * Movimientos que dependen de un portafolio (para impedir borrarlo con dinero).
     */
    public function usage(int $id, int $userId): int
    {
        $total = 0;

        foreach (['aportaciones_inversion', 'retiros_inversion', 'inversiones_rendimientos', 'inversiones_posiciones'] as $table) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = ? AND portafolio_id = ?");
            $stmt->execute([$userId, $id]);
            $total += (int) $stmt->fetchColumn();
        }

        return $total;
    }

    /**
     * Aportaciones (`in`) y retiros (`out`) en moneda principal.
     *
     * @return list<array<string, mixed>>
     */
    public function movements(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT 'in' AS kind, portafolio_id, posicion_id, instrumento,
                    COALESCE(monto_principal_centavos, monto_centavos) AS monto, fecha
             FROM aportaciones_inversion WHERE user_id = ?
             UNION ALL
             SELECT 'out' AS kind, portafolio_id, posicion_id, instrumento,
                    COALESCE(monto_principal_centavos, monto_centavos) AS monto, fecha
             FROM retiros_inversion WHERE user_id = ?"
        );
        $stmt->execute([$userId, $userId]);

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function yields(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, COALESCE(r.monto_principal_centavos, r.monto_centavos) AS monto
             FROM inversiones_rendimientos r
             WHERE r.user_id = ?
             ORDER BY r.fecha DESC, r.id DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findYield(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM inversiones_rendimientos WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createYield(int $userId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO inversiones_rendimientos
                (user_id, portafolio_id, posicion_id, tipo, monto_centavos, moneda, monto_principal_centavos,
                 destino, ingreso_id, fecha, nota, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $userId,
            $data['portafolio_id'],
            $data['posicion_id'],
            $data['tipo'],
            $data['monto_centavos'],
            $data['moneda'],
            $data['monto_principal_centavos'],
            $data['destino'],
            $data['ingreso_id'] ?? null,
            $data['fecha'],
            $data['nota'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateYield(int $id, int $userId, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE inversiones_rendimientos SET
                tipo = ?, monto_centavos = ?, moneda = ?, monto_principal_centavos = ?, fecha = ?, nota = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            $data['tipo'],
            $data['monto_centavos'],
            $data['moneda'],
            $data['monto_principal_centavos'],
            $data['fecha'],
            $data['nota'],
            $id,
            $userId,
        ]);
    }

    public function deleteYield(int $id, int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM inversiones_rendimientos WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }

    public function movePosition(int $positionId, int $userId, int $portfolioId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE inversiones_posiciones SET portafolio_id = ?, updated_at = NOW() WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$portfolioId, $positionId, $userId]);

        // Lo que estaba ligado al instrumento lo acompaña al nuevo portafolio.
        foreach (['aportaciones_inversion', 'retiros_inversion', 'inversiones_rendimientos'] as $table) {
            $stmt = $this->db->prepare("UPDATE {$table} SET portafolio_id = ? WHERE posicion_id = ? AND user_id = ?");
            $stmt->execute([$portfolioId, $positionId, $userId]);
        }
    }

    /**
     * Claves antiguas (`cetes`, `u12`…) usadas en movimientos sin portafolio.
     *
     * @return list<string>
     */
    public function legacyKeys(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT instrumento FROM aportaciones_inversion WHERE user_id = ? AND portafolio_id IS NULL
             UNION
             SELECT instrumento FROM retiros_inversion WHERE user_id = ? AND portafolio_id IS NULL'
        );
        $stmt->execute([$userId, $userId]);

        return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    /**
     * @return list<string>
     */
    public function unassignedPositionTypes(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT DISTINCT tipo FROM inversiones_posiciones WHERE user_id = ? AND portafolio_id IS NULL'
        );
        $stmt->execute([$userId]);

        return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function assignLegacyMovements(int $userId, string $key, int $portfolioId): void
    {
        foreach (['aportaciones_inversion', 'retiros_inversion'] as $table) {
            $stmt = $this->db->prepare(
                "UPDATE {$table} SET portafolio_id = ? WHERE user_id = ? AND instrumento = ? AND portafolio_id IS NULL"
            );
            $stmt->execute([$portfolioId, $userId, $key]);
        }
    }

    /**
     * @param list<string> $types
     */
    public function assignPositionsByType(int $userId, array $types, int $portfolioId): void
    {
        if ($types === []) {
            return;
        }

        $marks = implode(', ', array_fill(0, count($types), '?'));
        $stmt = $this->db->prepare(
            "UPDATE inversiones_posiciones SET portafolio_id = ?
             WHERE user_id = ? AND portafolio_id IS NULL AND tipo IN ({$marks})"
        );
        $stmt->execute(array_merge([$portfolioId, $userId], $types));
    }
}
