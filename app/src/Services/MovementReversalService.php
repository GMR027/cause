<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Repositories\DebtRepository;
use Cauce\Repositories\NoControladoRepository;
use PDO;

/**
 * Deshace los efectos de un gasto y los movimientos que salieron de un
 * ingreso.
 *
 * - Al borrar un gasto, su dinero vuelve a estar disponible y se deshace lo
 *   que provocó: el pago de deuda (el saldo vuelve), el retiro de ahorro de
 *   un deseo comprado (el dinero vuelve a la bolsa y el deseo se reactiva) y
 *   el exceso que se mandó a una deuda.
 * - Al borrar un ingreso con «borrar también sus movimientos», se elimina
 *   todo lo que salió de ese dinero en el período: gastos, aportes a ahorro e
 *   inversión, donaciones, Compartir, pagos de deuda y movimientos entre
 *   categorías. Se conservan los retiros y traspasos entre bolsas (usan dinero
 *   ahorrado antes) y los saldos iniciales de ahorro.
 */
final class MovementReversalService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * Deshace los efectos ligados a un gasto. Debe llamarse dentro de la
     * transacción que lo borra, antes del DELETE.
     *
     * @param array<string, mixed> $gasto
     */
    public function revertGastoEffects(array $gasto): void
    {
        $gastoId = (int) $gasto['id'];
        $userId = (int) $gasto['user_id'];
        $debts = new DebtRepository($this->db);

        foreach ($debts->paymentsForGasto($gastoId, $userId) as $payment) {
            $debts->revertPayment($payment);
        }

        if (!empty($gasto['deuda_exceso_id']) && (int) ($gasto['deuda_exceso_centavos'] ?? 0) > 0) {
            $debts->revertExcess((int) $gasto['deuda_exceso_id'], $userId, (int) $gasto['deuda_exceso_centavos']);
        }

        // Retiro de ahorro con el que se pagó (p. ej. comprar un deseo con su ahorro).
        $stmt = $this->db->prepare('SELECT * FROM retiros_ahorro WHERE gasto_asociado_id = ? AND user_id = ?');
        $stmt->execute([$gastoId, $userId]);

        foreach ($stmt->fetchAll() as $withdrawal) {
            $this->db->prepare('DELETE FROM retiros_ahorro WHERE id = ? AND user_id = ?')
                ->execute([(int) $withdrawal['id'], $userId]);

            if (!empty($withdrawal['meta_id'])) {
                $this->db->prepare('UPDATE metas_ahorro SET activa = 1, updated_at = NOW() WHERE id = ? AND user_id = ?')
                    ->execute([(int) $withdrawal['meta_id'], $userId]);
            }

            $this->db->prepare('UPDATE cuentas_ahorro SET activa = 1, updated_at = NOW() WHERE id = ? AND user_id = ?')
                ->execute([(int) $withdrawal['cuenta_id'], $userId]);
        }

        // Deseo marcado como comprado con este gasto: vuelve a la lista.
        $this->db->prepare(
            "UPDATE lista_deseos SET estado = 'activo', gasto_id = NULL, comprado_at = NULL, updated_at = NOW()
             WHERE gasto_id = ? AND user_id = ? AND estado = 'comprado'"
        )->execute([$gastoId, $userId]);

        $noControlado = new NoControladoRepository($this->db);
        $noControlado->deleteByReferencia($userId, 'gasto_excedido', $gastoId);
        $noControlado->deleteByReferencia($userId, 'otro', $gastoId);
    }

    /**
     * Cuántos movimientos salieron del dinero de un período, por tipo.
     *
     * @return array<string, int>
     */
    public function countForPeriod(int $userId, string $inicio, string $fin): array
    {
        $counts = [];

        foreach ($this->periodQueries($userId, $inicio, $fin) as $kind => [$where, $params, $table]) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $where);
            $stmt->execute($params);
            $counts[$kind] = (int) $stmt->fetchColumn();
        }

        $counts['deuda'] = count((new DebtRepository($this->db))->paymentsWithoutExpense($userId, $inicio, $fin));

        return array_filter($counts);
    }

    /**
     * Borra todo lo que salió del dinero del período. Debe correr dentro de
     * una transacción.
     */
    public function deleteForPeriod(int $userId, string $inicio, string $fin): int
    {
        $deleted = 0;
        $debts = new DebtRepository($this->db);

        // Gastos: uno por uno para deshacer pagos de deuda, deseos y retiros.
        $stmt = $this->db->prepare(
            "SELECT * FROM gastos WHERE user_id = ? AND periodo_inicio = ? AND periodo_fin = ? AND estado <> 'cerrado'"
        );
        $stmt->execute([$userId, $inicio, $fin]);

        foreach ($stmt->fetchAll() as $gasto) {
            $this->revertGastoEffects($gasto);
            $this->db->prepare('DELETE FROM gastos WHERE id = ? AND user_id = ?')->execute([(int) $gasto['id'], $userId]);
            $deleted++;
        }

        foreach ($debts->paymentsWithoutExpense($userId, $inicio, $fin) as $payment) {
            $debts->revertPayment($payment);
            $deleted++;
        }

        foreach ($this->periodQueries($userId, $inicio, $fin) as $kind => [$where, $params, $table]) {
            if ($kind === 'gasto') {
                continue;
            }

            $stmt = $this->db->prepare('DELETE FROM ' . $table . ' WHERE ' . $where);
            $stmt->execute($params);
            $deleted += $stmt->rowCount();
        }

        // Las decisiones de «no controlado» del período ya no aplican.
        $this->db->prepare(
            'UPDATE gastos_recurrentes SET no_controlado_hasta = NULL WHERE user_id = ? AND no_controlado_hasta = ?'
        )->execute([$userId, $fin]);

        return $deleted;
    }

    /**
     * @return array<string, array{0: string, 1: list<mixed>, 2: string}>
     */
    private function periodQueries(int $userId, string $inicio, string $fin): array
    {
        return [
            'gasto' => [
                "user_id = ? AND periodo_inicio = ? AND periodo_fin = ? AND estado <> 'cerrado'",
                [$userId, $inicio, $fin],
                'gastos',
            ],
            'ahorro' => [
                'user_id = ? AND fecha BETWEEN ? AND ? AND es_saldo_inicial = 0 AND es_reasignacion = 0',
                [$userId, $inicio, $fin],
                'aportaciones_ahorro',
            ],
            // Las aportaciones extra que nacen de un rebalanceo (retiro + aporte
            // del mismo monto y fecha) son dinero ya invertido: se conservan.
            'inversion' => [
                "user_id = ? AND fecha BETWEEN ? AND ? AND NOT (es_extra = 1 AND EXISTS (
                    SELECT 1 FROM retiros_inversion r
                    WHERE r.user_id = aportaciones_inversion.user_id AND r.motivo_catalogo = 'rebalanceo'
                      AND r.fecha = aportaciones_inversion.fecha AND r.monto_centavos = aportaciones_inversion.monto_centavos
                ))",
                [$userId, $inicio, $fin],
                'aportaciones_inversion',
            ],
            'donacion' => ['user_id = ? AND fecha BETWEEN ? AND ?', [$userId, $inicio, $fin], 'donaciones'],
            'compartir' => ['user_id = ? AND fecha BETWEEN ? AND ?', [$userId, $inicio, $fin], 'compartidos'],
            'movimiento' => [
                'user_id = ? AND periodo_inicio = ? AND periodo_fin = ?',
                [$userId, $inicio, $fin],
                'movimientos',
            ],
        ];
    }
}
