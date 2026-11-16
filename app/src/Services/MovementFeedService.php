<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use PDO;

/**
 * Lista unificada de todo lo que mueve dinero en un período: ingresos,
 * gastos, aportes y retiros de ahorro e inversión, traspasos entre bolsas,
 * donaciones, Compartir y movimientos entre categorías del método.
 *
 * Cada fila tiene la misma forma para pintarla en /movimientos:
 * tipo, id, fecha, titulo, detalle, monto_centavos, moneda,
 * monto_principal_centavos, direccion (entrada|salida|neutral) y url.
 */
final class MovementFeedService
{
    /** Tipos que el filtro de /movimientos puede pedir (además de «gasto»). */
    public const KINDS = ['ingreso', 'gasto', 'ahorro', 'inversion', 'donacion', 'compartir', 'movimiento'];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * Filas que no son gastos (los gastos los aporta GastoRepository).
     *
     * @param array{periodo_inicio?: string, periodo_fin?: string, q?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function nonExpenseRows(int $userId, string $kind, array $filters): array
    {
        $inicio = (string) ($filters['periodo_inicio'] ?? '');
        $fin = (string) ($filters['periodo_fin'] ?? '');
        $q = trim((string) ($filters['q'] ?? ''));
        $all = $kind === '' || $kind === 'todos';
        $rows = [];

        if ($all || $kind === 'ingreso') {
            $rows = array_merge($rows, $this->incomes($userId, $inicio, $fin, $q));
        }

        if ($all || $kind === 'ahorro') {
            $rows = array_merge($rows, $this->savings($userId, $inicio, $fin, $q));
        }

        if ($all || $kind === 'inversion') {
            $rows = array_merge($rows, $this->investments($userId, $inicio, $fin, $q));
        }

        if ($all || $kind === 'donacion') {
            $rows = array_merge($rows, $this->donations($userId, $inicio, $fin, $q));
        }

        if ($all || $kind === 'compartir') {
            $rows = array_merge($rows, $this->sharing($userId, $inicio, $fin, $q));
        }

        if ($all || $kind === 'movimiento') {
            $rows = array_merge($rows, $this->moves($userId, $inicio, $fin, $q));
        }

        return $rows;
    }

    /**
     * Ordena por fecha descendente (y por id para empates).
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function sort(array $rows): array
    {
        usort($rows, static function (array $a, array $b): int {
            return [(string) $b['fecha'], (int) $b['id']] <=> [(string) $a['fecha'], (int) $a['id']];
        });

        return $rows;
    }

    /**
     * Totales de entradas y salidas en moneda principal.
     *
     * @param list<array<string, mixed>> $rows
     * @return array{entradas: int, salidas: int}
     */
    public static function totals(array $rows): array
    {
        $totals = ['entradas' => 0, 'salidas' => 0];

        foreach ($rows as $row) {
            $amount = (int) ($row['monto_principal_centavos'] ?? $row['monto_centavos']);

            if ($row['direccion'] === 'entrada') {
                $totals['entradas'] += $amount;
            } elseif ($row['direccion'] === 'salida') {
                $totals['salidas'] += $amount;
            }
        }

        return $totals;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function incomes(int $userId, string $inicio, string $fin, string $q): array
    {
        [$where, $params] = $this->base($userId, $inicio, $fin, 'periodo');

        if ($q !== '') {
            $where[] = 'nota LIKE ?';
            $params[] = $this->like($q);
        }

        $rows = [];

        foreach ($this->select('SELECT * FROM ingresos', $where, $params) as $row) {
            $detail = $this->joinText(__('income.types.' . (string) $row['tipo']), $row['nota'] ?? null);
            $rows[] = $this->row('ingreso', $row, __('expenses.feed.income'), $detail, 'entrada', '/ingresos/ver?id=' . (int) $row['id']);
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function savings(int $userId, string $inicio, string $fin, string $q): array
    {
        $rows = [];
        [$where, $params] = $this->base($userId, $inicio, $fin, 'a.fecha');
        $where[] = 'a.es_reasignacion = 0';

        if ($q !== '') {
            $where[] = '(c.nombre LIKE ? OR m.nombre LIKE ?)';
            $params[] = $this->like($q);
            $params[] = $this->like($q);
        }

        $sql = 'SELECT a.*, c.nombre AS cuenta_nombre, m.nombre AS meta_nombre
                FROM aportaciones_ahorro a
                LEFT JOIN cuentas_ahorro c ON c.id = a.cuenta_id
                LEFT JOIN metas_ahorro m ON m.id = a.meta_id';

        foreach ($this->select($sql, $where, $params, 'a.') as $row) {
            $title = (int) $row['es_saldo_inicial'] === 1
                ? __('expenses.feed.savings_initial')
                : __('expenses.feed.savings_in');
            // El dinero que ya tenía ahorrado no sale del ingreso del período.
            $direction = (int) $row['es_saldo_inicial'] === 1 ? 'neutral' : 'salida';
            $rows[] = $this->row('ahorro', $row, $title, $this->joinText($row['meta_nombre'] ?? null, $row['cuenta_nombre'] ?? null), $direction, '/ahorro');
        }

        [$where, $params] = $this->base($userId, $inicio, $fin, 'r.fecha');
        $where[] = 'r.es_reasignacion = 0';

        if ($q !== '') {
            $where[] = '(c.nombre LIKE ? OR m.nombre LIKE ? OR r.motivo_texto LIKE ?)';
            $params[] = $this->like($q);
            $params[] = $this->like($q);
            $params[] = $this->like($q);
        }

        $sql = 'SELECT r.*, c.nombre AS cuenta_nombre, m.nombre AS meta_nombre
                FROM retiros_ahorro r
                LEFT JOIN cuentas_ahorro c ON c.id = r.cuenta_id
                LEFT JOIN metas_ahorro m ON m.id = r.meta_id';

        foreach ($this->select($sql, $where, $params, 'r.') as $row) {
            $detail = $this->joinText(
                __('savings.reasons.' . (string) $row['motivo_catalogo']),
                $row['meta_nombre'] ?? $row['cuenta_nombre'] ?? null
            );
            $rows[] = $this->row('ahorro', $row, __('expenses.feed.savings_out'), $detail, 'entrada', '/ahorro');
        }

        [$where, $params] = $this->base($userId, $inicio, $fin, 't.fecha');

        if ($q !== '') {
            $where[] = '(o.nombre LIKE ? OR d.nombre LIKE ? OR t.nota LIKE ?)';
            $params[] = $this->like($q);
            $params[] = $this->like($q);
            $params[] = $this->like($q);
        }

        $sql = 'SELECT t.*, o.nombre AS origen_nombre, d.nombre AS destino_nombre
                FROM transferencias_ahorro t
                LEFT JOIN cuentas_ahorro o ON o.id = t.cuenta_origen_id
                LEFT JOIN cuentas_ahorro d ON d.id = t.cuenta_destino_id';

        foreach ($this->select($sql, $where, $params, 't.') as $row) {
            $detail = (string) ($row['origen_nombre'] ?? '') . ' → ' . (string) ($row['destino_nombre'] ?? '');
            $rows[] = $this->row('ahorro', $row, __('expenses.feed.savings_transfer'), $detail, 'neutral', '/ahorro');
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function investments(int $userId, string $inicio, string $fin, string $q): array
    {
        $labels = (new InvestmentService())->customInstruments($userId);
        $portfolios = $this->names('SELECT id, nombre FROM inversiones_portafolios WHERE user_id = ?', $userId);
        $positions = $this->names('SELECT id, nombre FROM inversiones_posiciones WHERE user_id = ?', $userId);
        $label = static function (array $row) use ($labels, $portfolios, $positions): string {
            $portfolio = $portfolios[(int) ($row['portafolio_id'] ?? 0)] ?? null;

            if ($portfolio === null) {
                $key = (string) ($row['instrumento'] ?? 'otro');

                return (string) ($labels[$key] ?? __('investment.instruments.' . $key));
            }

            $position = $positions[(int) ($row['posicion_id'] ?? 0)] ?? null;

            return $position !== null ? $portfolio . ' · ' . $position : $portfolio;
        };
        $rows = [];

        [$where, $params] = $this->base($userId, $inicio, $fin, 'fecha');

        foreach ($this->select('SELECT * FROM aportaciones_inversion', $where, $params) as $row) {
            $instrument = $label($row);

            if ($q !== '' && mb_stripos($instrument, $q) === false) {
                continue;
            }

            $detail = $this->joinText($instrument, (int) $row['es_extra'] === 1 ? __('investment.extra_short') : null);
            $rows[] = $this->row('inversion', $row, __('expenses.feed.investment_in'), $detail, 'salida', '/inversion');
        }

        [$where, $params] = $this->base($userId, $inicio, $fin, 'fecha');

        foreach ($this->select('SELECT * FROM retiros_inversion', $where, $params) as $row) {
            $instrument = $label($row);
            $text = (string) ($row['motivo_texto'] ?? '');

            if ($q !== '' && mb_stripos($instrument . ' ' . $text, $q) === false) {
                continue;
            }

            $detail = $this->joinText($instrument, __('investment.reasons.' . (string) $row['motivo_catalogo']));
            $rows[] = $this->row('inversion', $row, __('expenses.feed.investment_out'), $detail, 'entrada', '/inversion');
        }

        [$where, $params] = $this->base($userId, $inicio, $fin, 'fecha');

        foreach ($this->select('SELECT * FROM inversiones_rendimientos', $where, $params) as $row) {
            $instrument = $label($row);
            $note = (string) ($row['nota'] ?? '');
            $type = (string) $row['tipo'];

            if ($q !== '' && mb_stripos($instrument . ' ' . $note, $q) === false) {
                continue;
            }

            // Siempre neutral: un rendimiento pagado ya aparece como su ingreso extra.
            $loss = in_array($type, ['baja', 'comision'], true);
            $detail = $this->joinText(
                $instrument,
                $loss ? null : __('investment.yields.destinations.' . (string) $row['destino']),
                $note
            );
            $rows[] = $this->row(
                'inversion',
                $row,
                __('investment.yields.types.' . $type),
                $detail,
                'neutral',
                '/inversion'
            );
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function names(string $sql, int $userId): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $names = [];

        foreach ($stmt->fetchAll() as $row) {
            $names[(int) $row['id']] = (string) $row['nombre'];
        }

        return $names;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function donations(int $userId, string $inicio, string $fin, string $q): array
    {
        [$where, $params] = $this->base($userId, $inicio, $fin, 'fecha');

        if ($q !== '') {
            $where[] = 'causa LIKE ?';
            $params[] = $this->like($q);
        }

        $rows = [];

        foreach ($this->select('SELECT * FROM donaciones', $where, $params) as $row) {
            $detail = $this->joinText(
                (string) ($row['causa'] ?? __('donation.no_cause')),
                (int) $row['es_extra'] === 1 ? __('donation.extra_short') : null
            );
            $rows[] = $this->row('donacion', $row, __('donation.title'), $detail, 'salida', '/donacion');
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sharing(int $userId, string $inicio, string $fin, string $q): array
    {
        [$where, $params] = $this->base($userId, $inicio, $fin, 'fecha');

        if ($q !== '') {
            $where[] = 'destinatario LIKE ?';
            $params[] = $this->like($q);
        }

        $rows = [];

        foreach ($this->select('SELECT * FROM compartidos', $where, $params) as $row) {
            $detail = $this->joinText(
                (string) ($row['destinatario'] ?? __('sharing.no_recipient')),
                (int) $row['es_extra'] === 1 ? __('sharing.extra_short') : null
            );
            $rows[] = $this->row('compartir', $row, __('sharing.title'), $detail, 'salida', '/compartir');
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function moves(int $userId, string $inicio, string $fin, string $q): array
    {
        [$where, $params] = $this->base($userId, $inicio, $fin, 'periodo');

        if ($q !== '') {
            $where[] = '(origen LIKE ? OR destino LIKE ? OR motivo_texto LIKE ?)';
            $params[] = $this->like($q);
            $params[] = $this->like($q);
            $params[] = $this->like($q);
        }

        $rows = [];

        foreach ($this->select('SELECT * FROM movimientos', $where, $params) as $row) {
            $detail = $this->joinText(
                (string) $row['origen'] . ' → ' . (string) $row['destino'],
                __('moves.reasons.' . (string) $row['motivo_catalogo'])
            );
            $rows[] = $this->row('movimiento', $row, __('expenses.feed.move'), $detail, 'neutral', '/mover');
        }

        return $rows;
    }

    /**
     * Filtro común por usuario y período. Con $dateColumn = 'periodo' usa las
     * columnas periodo_inicio/periodo_fin de la tabla (ingresos, movimientos);
     * si no, filtra por esa fecha dentro del rango.
     *
     * @return array{0: list<string>, 1: list<mixed>}
     */
    private function base(int $userId, string $inicio, string $fin, string $dateColumn): array
    {
        $prefix = str_contains($dateColumn, '.') ? explode('.', $dateColumn)[0] . '.' : '';
        $where = [$prefix . 'user_id = ?'];
        $params = [$userId];

        if ($inicio !== '' && $fin !== '') {
            if ($dateColumn === 'periodo') {
                $where[] = 'periodo_inicio = ? AND periodo_fin = ?';
            } else {
                $where[] = $dateColumn . ' BETWEEN ? AND ?';
            }

            $params[] = $inicio;
            $params[] = $fin;
        }

        return [$where, $params];
    }

    /**
     * @param list<string> $where
     * @param list<mixed> $params
     * @return list<array<string, mixed>>
     */
    private function select(string $sql, array $where, array $params, string $prefix = ''): array
    {
        $stmt = $this->db->prepare(
            $sql . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY ' . $prefix . 'fecha DESC, ' . $prefix . 'id DESC LIMIT 200'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function row(string $kind, array $row, string $title, string $detail, string $direction, ?string $url): array
    {
        return [
            'tipo' => $kind,
            'id' => (int) $row['id'],
            'fecha' => (string) $row['fecha'],
            'titulo' => $title,
            'detalle' => $detail,
            'monto_centavos' => (int) $row['monto_centavos'],
            'monto_principal_centavos' => $row['monto_principal_centavos'] !== null
                ? (int) $row['monto_principal_centavos']
                : (int) $row['monto_centavos'],
            'moneda' => (string) $row['moneda'],
            'direccion' => $direction,
            'url' => $url,
        ];
    }

    private function joinText(?string ...$parts): string
    {
        return implode(' · ', array_filter(
            array_map(static fn (?string $part): string => trim((string) $part), $parts),
            static fn (string $part): bool => $part !== ''
        ));
    }

    private function like(string $q): string
    {
        return '%' . addcslashes($q, '%_\\') . '%';
    }
}
