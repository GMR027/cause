<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use Cauce\Services\CurrencyService;
use PDO;

/**
 * Persistencia de la lista de deseos.
 */
final class WishlistRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allForUser(int $userId, ?string $state = null): array
    {
        $where = ['user_id = ?'];
        $params = [$userId];

        if ($state !== null && in_array($state, ['activo', 'comprado', 'descartado'], true)) {
            $where[] = 'estado = ?';
            $params[] = $state;
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM lista_deseos WHERE ' . implode(' AND ', $where) . '
             ORDER BY FIELD(estado, "activo", "comprado", "descartado"), prioridad ASC, created_at DESC, id DESC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM lista_deseos WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array{
     *     user_id: int, nombre: string, monto_estimado_centavos: int, moneda: string,
     *     prioridad: int, fecha_objetivo: ?string, fecha_enfriamiento: ?string, nota: ?string
     * } $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO lista_deseos
                (user_id, nombre, monto_estimado_centavos, monto_estimado_principal_centavos,
                 moneda, prioridad, fecha_objetivo, fecha_enfriamiento, estado, nota, created_at, updated_at)
             VALUES
                (:user_id, :nombre, :monto, :monto_principal, :moneda, :prioridad,
                 :fecha_objetivo, :fecha_enfriamiento, "activo", :nota, NOW(), NOW())'
        );
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':nombre' => $data['nombre'],
            ':monto' => $data['monto_estimado_centavos'],
            ':monto_principal' => (new CurrencyService())->toPrincipalForUserId(
                (int) $data['user_id'],
                (int) $data['monto_estimado_centavos'],
                (string) $data['moneda'],
                gmdate('Y-m-d')
            ),
            ':moneda' => $data['moneda'],
            ':prioridad' => $data['prioridad'],
            ':fecha_objetivo' => $data['fecha_objetivo'],
            ':fecha_enfriamiento' => $data['fecha_enfriamiento'],
            ':nota' => $data['nota'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array{
     *     nombre: string, monto_estimado_centavos: int, prioridad: int,
     *     fecha_objetivo: ?string, fecha_enfriamiento: ?string, nota: ?string
     * } $data
     */
    public function update(int $id, int $userId, array $data): void
    {
        $existing = $this->findForUser($id, $userId);
        $currency = (string) ($data['moneda'] ?? $existing['moneda'] ?? 'MXN');
        $stmt = $this->db->prepare(
            'UPDATE lista_deseos SET
                nombre = ?, monto_estimado_centavos = ?, monto_estimado_principal_centavos = ?,
                moneda = ?, prioridad = ?, fecha_objetivo = ?, fecha_enfriamiento = ?, nota = ?,
                updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            $data['nombre'],
            $data['monto_estimado_centavos'],
            (new CurrencyService())->toPrincipalForUserId($userId, (int) $data['monto_estimado_centavos'], $currency, gmdate('Y-m-d')),
            $currency,
            $data['prioridad'],
            $data['fecha_objetivo'],
            $data['fecha_enfriamiento'],
            $data['nota'],
            $id,
            $userId,
        ]);
    }

    public function markPurchased(int $id, int $userId, ?int $gastoId, string $purchasedAt): void
    {
        $stmt = $this->db->prepare(
            'UPDATE lista_deseos SET
                estado = "comprado", gasto_id = COALESCE(?, gasto_id), comprado_at = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$gastoId, $purchasedAt, $id, $userId]);
    }

    public function discard(int $id, int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE lista_deseos SET estado = "descartado", updated_at = NOW()
             WHERE id = ? AND user_id = ? AND estado = "activo"'
        );
        $stmt->execute([$id, $userId]);
    }

    public function activeCount(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM lista_deseos WHERE user_id = ? AND estado = "activo"'
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    public function activeEstimatedTotal(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(monto_estimado_principal_centavos), 0)
             FROM lista_deseos WHERE user_id = ? AND estado = "activo"'
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }
}
