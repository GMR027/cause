<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use PDO;

/**
 * Búsqueda global unificada.
 */
final class SearchService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $user, array $filters): array
    {
        $userId = (int) $user['id'];
        $q = trim((string) ($filters['q'] ?? ''));
        $start = (string) ($filters['periodo_inicio'] ?? '');
        $end = (string) ($filters['periodo_fin'] ?? '');
        $dateStart = (string) ($filters['fecha_inicio'] ?? '');
        $dateEnd = (string) ($filters['fecha_fin'] ?? '');
        $rangeStart = $dateStart !== '' ? $dateStart : $start;
        $rangeEnd = $dateEnd !== '' ? $dateEnd : $end;
        $category = trim((string) ($filters['categoria'] ?? ''));
        $tagId = (int) ($filters['tag_id'] ?? 0);
        $medium = trim((string) ($filters['medio'] ?? ''));
        $min = isset($filters['monto_min']) && $filters['monto_min'] !== '' ? (int) $filters['monto_min'] : null;
        $max = isset($filters['monto_max']) && $filters['monto_max'] !== '' ? (int) $filters['monto_max'] : null;
        $cents = parse_money_cents($q);
        $results = [];
        $tagOrMedium = $tagId > 0 || $medium !== '';

        if ($tagOrMedium) {
            // Etiquetas y medios de pago solo existen en gastos.
            $results = $this->expenses($userId, $q, $cents, $start, $end, $dateStart, $dateEnd, $category, $tagId, $medium, $min, $max);
        } else {
            $results = array_merge(
                $results,
                $this->expenses($userId, $q, $cents, $start, $end, $dateStart, $dateEnd, $category, $tagId, $medium, $min, $max),
                $this->incomes($userId, $q, $cents, $start, $end, $dateStart, $dateEnd, $min, $max)
            );

            if ($category === '') {
                $results = array_merge(
                    $results,
                    $this->savings($userId, $q, $cents, $rangeStart, $rangeEnd, $min, $max),
                    $this->withdrawals('retiros_ahorro', 'retiro_ahorro', __('savings.actions.withdraw'), '/ahorro', $userId, $q, $cents, $rangeStart, $rangeEnd, $min, $max),
                    $this->investments($userId, $q, $cents, $rangeStart, $rangeEnd, $min, $max),
                    $this->withdrawals('retiros_inversion', 'retiro_inversion', __('investment.actions.withdraw'), '/inversion', $userId, $q, $cents, $rangeStart, $rangeEnd, $min, $max),
                    $this->donations($userId, $q, $cents, $rangeStart, $rangeEnd, $min, $max),
                    $this->goals($userId, $q, $min, $max),
                    $this->wishlist($userId, $q, $min, $max),
                    $this->debts($userId, $q, $min, $max),
                    $this->debtPayments($userId, $q, $cents, $rangeStart, $rangeEnd, $min, $max)
                );
            }

            $results = array_merge($results, $this->moves($userId, $q, $cents, $start, $end, $dateStart, $dateEnd, $category, $min, $max));
        }

        usort($results, static function (array $a, array $b): int {
            return strcmp((string) $b['fecha'], (string) $a['fecha']);
        });

        return array_slice($results, 0, 80);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function expenses(
        int $userId,
        string $q,
        ?int $cents,
        string $start,
        string $end,
        string $dateStart,
        string $dateEnd,
        string $category,
        int $tagId,
        string $medium,
        ?int $min,
        ?int $max
    ): array {
        $where = ['g.user_id = ?'];
        $params = [$userId];

        if ($start !== '' && $end !== '') {
            $where[] = 'g.periodo_inicio = ? AND g.periodo_fin = ?';
            $params[] = $start;
            $params[] = $end;
        }

        if ($dateStart !== '' && $dateEnd !== '') {
            $where[] = 'g.fecha BETWEEN ? AND ?';
            $params[] = $dateStart;
            $params[] = $dateEnd;
        }

        if ($category !== '') {
            $where[] = 'g.parte_metodo = ?';
            $params[] = $category;
        }

        if ($tagId > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM gasto_tags gt WHERE gt.gasto_id = g.id AND gt.tag_id = ?)';
            $params[] = $tagId;
        }

        if ($medium !== '') {
            $where[] = 'g.medio_pago = ?';
            $params[] = $medium;
        }

        if ($min !== null) {
            $where[] = 'g.monto_centavos >= ?';
            $params[] = $min;
        }

        if ($max !== null) {
            $where[] = 'g.monto_centavos <= ?';
            $params[] = $max;
        }

        if ($q !== '') {
            $like = $this->like($q);

            if (mb_strlen($q) >= 3) {
                $where[] = '(MATCH(g.nota, g.subcategoria_nombre) AGAINST (? IN NATURAL LANGUAGE MODE)
                    OR g.nota LIKE ? OR g.subcategoria_nombre LIKE ? OR g.monto_centavos = ?)';
                $params[] = $q;
                $params[] = $like;
                $params[] = $like;
                $params[] = $cents ?? -1;
            } else {
                $where[] = '(g.nota LIKE ? OR g.subcategoria_nombre LIKE ? OR g.monto_centavos = ?)';
                $params[] = $like;
                $params[] = $like;
                $params[] = $cents ?? -1;
            }
        }

        $stmt = $this->db->prepare(
            'SELECT g.id, g.fecha, g.monto_centavos, g.moneda, g.parte_metodo, g.subcategoria_nombre, g.nota, g.estado
             FROM gastos g WHERE ' . implode(' AND ', $where) . ' ORDER BY g.fecha DESC, g.id DESC LIMIT 40'
        );
        $stmt->execute($params);
        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $title = (string) $row['parte_metodo'] . ' · ' . (string) ($row['subcategoria_nombre'] ?? __('expenses.show.uncategorized'));
            $rows[] = [
                'tipo' => 'gasto',
                'id' => (int) $row['id'],
                'fecha' => (string) $row['fecha'],
                'titulo' => $title,
                'subtitulo' => (string) ($row['nota'] ?? ''),
                'monto_centavos' => (int) $row['monto_centavos'],
                'moneda' => (string) $row['moneda'],
                'url' => '/gastos/ver?id=' . (int) $row['id'],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function incomes(
        int $userId,
        string $q,
        ?int $cents,
        string $start,
        string $end,
        string $dateStart,
        string $dateEnd,
        ?int $min,
        ?int $max
    ): array {
        $where = ['user_id = ?'];
        $params = [$userId];

        if ($start !== '' && $end !== '') {
            $where[] = 'periodo_inicio = ? AND periodo_fin = ?';
            $params[] = $start;
            $params[] = $end;
        }

        if ($dateStart !== '' && $dateEnd !== '') {
            $where[] = 'fecha BETWEEN ? AND ?';
            $params[] = $dateStart;
            $params[] = $dateEnd;
        }

        if ($min !== null) {
            $where[] = 'monto_centavos >= ?';
            $params[] = $min;
        }

        if ($max !== null) {
            $where[] = 'monto_centavos <= ?';
            $params[] = $max;
        }

        if ($q !== '') {
            $like = $this->like($q);
            $where[] = '(nota LIKE ? OR monto_centavos = ?)';
            $params[] = $like;
            $params[] = $cents ?? -1;
        }

        $stmt = $this->db->prepare(
            'SELECT id, fecha, monto_centavos, moneda, tipo FROM ingresos WHERE '
            . implode(' AND ', $where) . ' ORDER BY fecha DESC, id DESC LIMIT 30'
        );
        $stmt->execute($params);
        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'tipo' => 'ingreso',
                'id' => (int) $row['id'],
                'fecha' => (string) $row['fecha'],
                'titulo' => __('income.nuevo.title'),
                'subtitulo' => __('income.types.' . (string) $row['tipo']),
                'monto_centavos' => (int) $row['monto_centavos'],
                'moneda' => (string) $row['moneda'],
                'url' => '/ingresos/acreditar?id=' . (int) $row['id'],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function moves(
        int $userId,
        string $q,
        ?int $cents,
        string $start,
        string $end,
        string $dateStart,
        string $dateEnd,
        string $category,
        ?int $min,
        ?int $max
    ): array {
        $where = ['user_id = ?'];
        $params = [$userId];

        if ($start !== '' && $end !== '') {
            $where[] = 'periodo_inicio = ? AND periodo_fin = ?';
            $params[] = $start;
            $params[] = $end;
        }

        if ($dateStart !== '' && $dateEnd !== '') {
            $where[] = 'fecha BETWEEN ? AND ?';
            $params[] = $dateStart;
            $params[] = $dateEnd;
        }

        if ($category !== '') {
            $where[] = '(origen = ? OR destino = ?)';
            $params[] = $category;
            $params[] = $category;
        }

        if ($min !== null) {
            $where[] = 'monto_centavos >= ?';
            $params[] = $min;
        }

        if ($max !== null) {
            $where[] = 'monto_centavos <= ?';
            $params[] = $max;
        }

        if ($q !== '') {
            $like = $this->like($q);
            $where[] = '(origen LIKE ? OR destino LIKE ? OR motivo_texto LIKE ? OR monto_centavos = ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $cents ?? -1;
        }

        $stmt = $this->db->prepare(
            'SELECT id, fecha, monto_centavos, moneda, origen, destino, motivo_catalogo FROM movimientos WHERE '
            . implode(' AND ', $where) . ' ORDER BY fecha DESC, id DESC LIMIT 30'
        );
        $stmt->execute($params);
        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'tipo' => 'movimiento',
                'id' => (int) $row['id'],
                'fecha' => (string) $row['fecha'],
                'titulo' => $row['origen'] . ' → ' . $row['destino'],
                'subtitulo' => __('moves.reasons.' . (string) $row['motivo_catalogo']),
                'monto_centavos' => (int) $row['monto_centavos'],
                'moneda' => (string) $row['moneda'],
                'url' => '/categorias/ver?parte=' . rawurlencode((string) $row['origen']),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function savings(int $userId, string $q, ?int $cents, string $start, string $end, ?int $min, ?int $max): array
    {
        return $this->financeRows(
            'aportaciones_ahorro',
            'ahorro',
            __('savings.actions.contribute'),
            $userId,
            $q,
            $cents,
            $start,
            $end,
            $min,
            $max
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function investments(int $userId, string $q, ?int $cents, string $start, string $end, ?int $min, ?int $max): array
    {
        return $this->financeRows(
            'aportaciones_inversion',
            'inversion',
            __('investment.actions.contribute'),
            $userId,
            $q,
            $cents,
            $start,
            $end,
            $min,
            $max
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function financeRows(
        string $table,
        string $type,
        string $title,
        int $userId,
        string $q,
        ?int $cents,
        string $start,
        string $end,
        ?int $min,
        ?int $max
    ): array {
        $where = ['user_id = ?'];
        $params = [$userId];

        if ($start !== '' && $end !== '') {
            $where[] = 'fecha BETWEEN ? AND ?';
            $params[] = $start;
            $params[] = $end;
        }

        if ($min !== null) {
            $where[] = 'monto_centavos >= ?';
            $params[] = $min;
        }

        if ($max !== null) {
            $where[] = 'monto_centavos <= ?';
            $params[] = $max;
        }

        if ($q !== '') {
            $like = $this->like($q);
            $where[] = '(monto_centavos = ?)';
            $params[] = $cents ?? -1;
        }

        $stmt = $this->db->prepare(
            'SELECT id, fecha, monto_centavos, moneda FROM ' . $table . ' WHERE '
            . implode(' AND ', $where) . ' ORDER BY fecha DESC, id DESC LIMIT 20'
        );
        $stmt->execute($params);
        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'tipo' => $type,
                'id' => (int) $row['id'],
                'fecha' => (string) $row['fecha'],
                'titulo' => $title,
                'subtitulo' => '',
                'monto_centavos' => (int) $row['monto_centavos'],
                'moneda' => (string) $row['moneda'],
                'url' => $type === 'ahorro' ? '/ahorro' : '/inversion',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function withdrawals(
        string $table,
        string $type,
        string $title,
        string $url,
        int $userId,
        string $q,
        ?int $cents,
        string $start,
        string $end,
        ?int $min,
        ?int $max
    ): array {
        $where = ['user_id = ?'];
        $params = [$userId];

        if ($start !== '' && $end !== '') {
            $where[] = 'fecha BETWEEN ? AND ?';
            $params[] = $start;
            $params[] = $end;
        }

        if ($min !== null) {
            $where[] = 'monto_centavos >= ?';
            $params[] = $min;
        }

        if ($max !== null) {
            $where[] = 'monto_centavos <= ?';
            $params[] = $max;
        }

        if ($q !== '') {
            $like = $this->like($q);
            $where[] = '(motivo_texto LIKE ? OR monto_centavos = ?)';
            $params[] = $like;
            $params[] = $cents ?? -1;
        }

        $stmt = $this->db->prepare(
            'SELECT id, fecha, monto_centavos, moneda, motivo_texto FROM ' . $table . ' WHERE '
            . implode(' AND ', $where) . ' ORDER BY fecha DESC, id DESC LIMIT 20'
        );
        $stmt->execute($params);
        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'tipo' => $type,
                'id' => (int) $row['id'],
                'fecha' => (string) $row['fecha'],
                'titulo' => $title,
                'subtitulo' => (string) ($row['motivo_texto'] ?? ''),
                'monto_centavos' => (int) $row['monto_centavos'],
                'moneda' => (string) $row['moneda'],
                'url' => $url,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function wishlist(int $userId, string $q, ?int $min, ?int $max): array
    {
        $where = ['user_id = ?'];
        $params = [$userId];

        if ($q !== '') {
            $where[] = '(nombre LIKE ? OR nota LIKE ?)';
            $like = $this->like($q);
            $params[] = $like;
            $params[] = $like;
        }

        if ($min !== null) {
            $where[] = 'monto_estimado_centavos >= ?';
            $params[] = $min;
        }

        if ($max !== null) {
            $where[] = 'monto_estimado_centavos <= ?';
            $params[] = $max;
        }

        $stmt = $this->db->prepare(
            'SELECT id, nombre, nota, monto_estimado_centavos, moneda, created_at FROM lista_deseos WHERE '
            . implode(' AND ', $where) . ' ORDER BY created_at DESC, id DESC LIMIT 20'
        );
        $stmt->execute($params);
        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'tipo' => 'deseo',
                'id' => (int) $row['id'],
                'fecha' => (string) $row['created_at'],
                'titulo' => (string) $row['nombre'],
                'subtitulo' => (string) ($row['nota'] ?? ''),
                'monto_centavos' => (int) $row['monto_estimado_centavos'],
                'moneda' => (string) $row['moneda'],
                'url' => '/deseos',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function debtPayments(
        int $userId,
        string $q,
        ?int $cents,
        string $start,
        string $end,
        ?int $min,
        ?int $max
    ): array {
        $where = ['p.user_id = ?'];
        $params = [$userId];

        if ($start !== '' && $end !== '') {
            $where[] = 'p.fecha BETWEEN ? AND ?';
            $params[] = $start;
            $params[] = $end;
        }

        if ($min !== null) {
            $where[] = 'p.monto_centavos >= ?';
            $params[] = $min;
        }

        if ($max !== null) {
            $where[] = 'p.monto_centavos <= ?';
            $params[] = $max;
        }

        if ($q !== '') {
            $like = $this->like($q);
            $where[] = '(d.nombre LIKE ? OR p.nota LIKE ? OR p.monto_centavos = ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $cents ?? -1;
        }

        $stmt = $this->db->prepare(
            'SELECT p.id, p.fecha, p.monto_centavos, p.moneda, p.nota, d.nombre AS deuda_nombre
             FROM pagos_deuda p JOIN deudas d ON d.id = p.deuda_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.fecha DESC, p.id DESC LIMIT 20'
        );
        $stmt->execute($params);
        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'tipo' => 'pago_deuda',
                'id' => (int) $row['id'],
                'fecha' => (string) $row['fecha'],
                'titulo' => __('debts.payment.expense_note', [':name' => (string) $row['deuda_nombre']]),
                'subtitulo' => (string) ($row['nota'] ?? ''),
                'monto_centavos' => (int) $row['monto_centavos'],
                'moneda' => (string) $row['moneda'],
                'url' => '/deudas',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function donations(int $userId, string $q, ?int $cents, string $start, string $end, ?int $min, ?int $max): array
    {
        $where = ['user_id = ?'];
        $params = [$userId];

        if ($start !== '' && $end !== '') {
            $where[] = 'fecha BETWEEN ? AND ?';
            $params[] = $start;
            $params[] = $end;
        }

        if ($min !== null) {
            $where[] = 'monto_centavos >= ?';
            $params[] = $min;
        }

        if ($max !== null) {
            $where[] = 'monto_centavos <= ?';
            $params[] = $max;
        }

        if ($q !== '') {
            $like = $this->like($q);
            $where[] = '(causa LIKE ? OR monto_centavos = ?)';
            $params[] = $like;
            $params[] = $cents ?? -1;
        }

        $stmt = $this->db->prepare(
            'SELECT id, fecha, monto_centavos, moneda, causa FROM donaciones WHERE '
            . implode(' AND ', $where) . ' ORDER BY fecha DESC, id DESC LIMIT 20'
        );
        $stmt->execute($params);
        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'tipo' => 'donacion',
                'id' => (int) $row['id'],
                'fecha' => (string) $row['fecha'],
                'titulo' => (string) ($row['causa'] ?? __('donation.no_cause')),
                'subtitulo' => __('donation.title'),
                'monto_centavos' => (int) $row['monto_centavos'],
                'moneda' => (string) $row['moneda'],
                'url' => '/donacion',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function goals(int $userId, string $q, ?int $min, ?int $max): array
    {
        $where = ['user_id = ?'];
        $params = [$userId];

        if ($q !== '') {
            $where[] = 'nombre LIKE ?';
            $params[] = $this->like($q);
        }

        if ($min !== null) {
            $where[] = 'objetivo_centavos >= ?';
            $params[] = $min;
        }

        if ($max !== null) {
            $where[] = 'objetivo_centavos <= ?';
            $params[] = $max;
        }

        $stmt = $this->db->prepare(
            'SELECT id, nombre, objetivo_centavos, moneda FROM metas_ahorro WHERE '
            . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT 15'
        );
        $stmt->execute($params);
        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'tipo' => 'meta',
                'id' => (int) $row['id'],
                'fecha' => gmdate('Y-m-d H:i:s'),
                'titulo' => (string) $row['nombre'],
                'subtitulo' => __('savings.title'),
                'monto_centavos' => (int) $row['objetivo_centavos'],
                'moneda' => (string) $row['moneda'],
                'url' => '/ahorro',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function debts(int $userId, string $q, ?int $min, ?int $max): array
    {
        $where = ['user_id = ?'];
        $params = [$userId];

        if ($q !== '') {
            $where[] = 'nombre LIKE ?';
            $params[] = $this->like($q);
        }

        if ($min !== null) {
            $where[] = 'saldo_actual_centavos >= ?';
            $params[] = $min;
        }

        if ($max !== null) {
            $where[] = 'saldo_actual_centavos <= ?';
            $params[] = $max;
        }

        $stmt = $this->db->prepare(
            'SELECT id, nombre, saldo_actual_centavos, moneda, fecha_limite FROM deudas WHERE '
            . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT 15'
        );
        $stmt->execute($params);
        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'tipo' => 'deuda',
                'id' => (int) $row['id'],
                'fecha' => (string) ($row['fecha_limite'] ?? gmdate('Y-m-d')),
                'titulo' => (string) $row['nombre'],
                'subtitulo' => __('search.types.deuda'),
                'monto_centavos' => (int) $row['saldo_actual_centavos'],
                'moneda' => (string) $row['moneda'],
                'url' => '/deudas',
            ];
        }

        return $rows;
    }

    private function like(string $query): string
    {
        return '%' . addcslashes($query, '%_\\') . '%';
    }
}
