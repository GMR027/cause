<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Repositories\GastoRepository;
use Cauce\Repositories\IngresoRepository;
use Cauce\Repositories\MetodoRepository;
use Cauce\Repositories\NoControladoRepository;
use Cauce\Repositories\PlantillaRepository;
use Cauce\Repositories\SubcategoriaRepository;
use Cauce\Repositories\UsuarioMetodoRepository;
use Cauce\Repositories\UserRepository;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Registro, edición, borrado y conciliación de gastos.
 */
final class GastoService
{
    private const FUENTES = [
        'ahorro',
        'inversion',
        'ingreso_no_registrado',
        'deuda',
        'no_lo_se',
        'no_aplica',
    ];

    /**
     * @return array{cubierto_centavos: int, exceso_centavos: int, excedido: bool}
     */
    public function calcularRepartoGasto(int $montoCentavos, int $disponibleCentavos): array
    {
        if ($montoCentavos <= 0) {
            throw new InvalidArgumentException('El monto del gasto debe ser mayor que cero.');
        }

        $disponibleCentavos = max(0, $disponibleCentavos);
        $cubierto = min($montoCentavos, $disponibleCentavos);
        $exceso = $montoCentavos - $cubierto;

        return [
            'cubierto_centavos' => $cubierto,
            'exceso_centavos' => $exceso,
            'excedido' => $exceso > 0,
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array{inicio: string, fin: string}
     */
    public function periodoActual(array $user): array
    {
        $period = (new PeriodoService())->calcularPeriodo($user);

        return [
            'inicio' => $period['inicio']->format('Y-m-d H:i:s'),
            'fin' => $period['fin']->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    public function categoriasActivas(array $user): array
    {
        $active = (new UsuarioMetodoRepository())->activeForUser((int) $user['id']);

        if ($active === null) {
            return [];
        }

        $method = (new MetodoRepository())->findById((int) $active['metodo_id']);

        return $method !== null ? $method['categorias'] : [];
    }

    /**
     * Categorías que pueden recibir gastos: solo las controladas.
     *
     * Ahorro, inversión y donación tienen su propio flujo (aportes, retiros y
     * donaciones). Permitir un "gasto" ahí descuadraba los saldos, porque no
     * descuenta el acumulado real de esos módulos.
     *
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    public function categoriasGasto(array $user): array
    {
        return array_values(array_filter(
            $this->categoriasActivas($user),
            static fn (array $category): bool => (string) ($category['tipo'] ?? '') === 'controlada'
        ));
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, array{tipo: string, asignado: int, gastado: int, disponible: int}>
     */
    public function disponiblePorParte(array $user): array
    {
        $period = $this->periodoActual($user);
        $repo = new GastoRepository();
        $userId = (int) $user['id'];
        $result = [];

        foreach ($this->categoriasActivas($user) as $category) {
            $name = (string) $category['nombre'];
            $assigned = $repo->sumAssignedForPart($userId, $name, $period['inicio'], $period['fin']);
            $spent = $repo->sumSpentForPart($userId, $name, $period['inicio'], $period['fin']);

            $result[$name] = [
                'tipo' => (string) $category['tipo'],
                'asignado' => $assigned,
                'gastado' => $spent,
                'disponible' => max(0, $assigned - $spent),
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public function crearManual(array $user, array $datos, ?string $fotoPath = null): array
    {
        $timezone = (new PeriodoService())->userTimezone($user);
        $fecha = $this->toUtc((string) ($datos['fecha'] ?? 'now'), $timezone);
        $period = $this->periodoActual($user);
        $this->assertWithinPeriod($fecha, $period);

        if (!(new IngresoRepository())->existsForPeriod((int) $user['id'], $period['inicio'], $period['fin'])) {
            throw new RuntimeException('sin_ingreso');
        }

        return $this->insertar($user, $datos, $period, $fecha->format('Y-m-d H:i:s'), $fotoPath, [
            'estado' => 'asignado',
            'confirmado' => 1,
            'es_recurrente' => 0,
            'gasto_recurrente_id' => null,
        ]);
    }

    /**
     * Crea un gasto manual pendiente de asignación, sin consumir disponible.
     *
     * Se usa cuando una compra de la lista de deseos se registra como gasto pero
     * la categoría no tiene disponible en el período actual.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public function crearPendienteManual(array $user, array $datos): array
    {
        $timezone = (new PeriodoService())->userTimezone($user);
        $fecha = $this->toUtc((string) ($datos['fecha'] ?? 'now'), $timezone);
        $period = $this->periodoActual($user);
        $this->assertWithinPeriod($fecha, $period);

        return $this->insertar($user, $datos, $period, $fecha->format('Y-m-d H:i:s'), null, [
            'estado' => 'pendiente_asignacion',
            'confirmado' => 0,
            'es_recurrente' => 0,
            'gasto_recurrente_id' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public function editar(
        array $user,
        int $gastoId,
        array $datos,
        ?string $nuevaFoto = null,
        bool $eliminarFoto = false
    ): array {
        $gasto = (new GastoRepository())->findForUser($gastoId, (int) $user['id']);

        if ($gasto === null) {
            throw new RuntimeException('El gasto no existe.');
        }

        if (!$this->puedeEditar($gasto, $user)) {
            throw new RuntimeException('periodo_cerrado');
        }

        $timezone = (new PeriodoService())->userTimezone($user);
        $fecha = $this->toUtc((string) ($datos['fecha'] ?? $gasto['fecha']), $timezone);
        $period = [
            'inicio' => (string) $gasto['periodo_inicio'],
            'fin' => (string) $gasto['periodo_fin'],
        ];
        $this->assertWithinPeriod($fecha, $period);

        $photo = (string) ($gasto['foto_path'] ?? '');
        $photos = new PhotoService();

        if ($nuevaFoto !== null) {
            $photos->delete($photo !== '' ? $photo : null);
            $photo = $nuevaFoto;
        } elseif ($eliminarFoto) {
            $photos->delete($photo !== '' ? $photo : null);
            $photo = '';
        }

        return $this->insertar($user, $datos, $period, $fecha->format('Y-m-d H:i:s'), $photo !== '' ? $photo : null, [
            'estado' => 'asignado',
            'confirmado' => (int) ($gasto['confirmado'] ?? 1),
            'es_recurrente' => (int) ($gasto['es_recurrente'] ?? 0),
            'gasto_recurrente_id' => $gasto['gasto_recurrente_id'] !== null ? (int) $gasto['gasto_recurrente_id'] : null,
            'update_id' => $gastoId,
            'exclude_id' => $gastoId,
        ]);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function eliminar(array $user, int $gastoId): void
    {
        $gasto = (new GastoRepository())->findForUser($gastoId, (int) $user['id']);

        if ($gasto === null) {
            return;
        }

        if (!$this->puedeEditar($gasto, $user)) {
            throw new RuntimeException('periodo_cerrado');
        }

        $db = Database::connection();

        try {
            $db->beginTransaction();
            (new NoControladoRepository($db))->deleteByReferencia((int) $user['id'], 'gasto_excedido', $gastoId);
            (new GastoRepository($db))->delete($gastoId, (int) $user['id']);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        (new PhotoService())->delete($gasto['foto_path'] !== null ? (string) $gasto['foto_path'] : null);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function conciliar(array $user, int $gastoId): array
    {
        $gasto = (new GastoRepository())->findForUser($gastoId, (int) $user['id']);

        if ($gasto === null) {
            throw new RuntimeException('El gasto no existe.');
        }

        if (($gasto['estado'] ?? '') !== 'pendiente_asignacion') {
            return ['gasto_id' => $gastoId, 'estado' => (string) $gasto['estado'], 'excedido' => false];
        }

        $repo = new GastoRepository();
        $userId = (int) $user['id'];
        $part = (string) $gasto['parte_metodo'];
        $inicio = (string) $gasto['periodo_inicio'];
        $fin = (string) $gasto['periodo_fin'];
        $monto = (int) $gasto['monto_centavos'];
        // El disponible y el exceso se comparan en moneda principal.
        $montoPrincipal = (int) ($gasto['monto_principal_centavos'] ?? $monto);
        $principal = (new CurrencyService())->principal($user);
        $assigned = $repo->sumAssignedForPart($userId, $part, $inicio, $fin);
        $spent = $repo->sumSpentForPart($userId, $part, $inicio, $fin);
        $available = max(0, $assigned - $spent);
        $split = $this->calcularRepartoGasto($montoPrincipal, $available);

        $db = Database::connection();

        try {
            $db->beginTransaction();
            (new GastoRepository($db))->update($gastoId, $userId, [
                'monto_centavos' => $monto,
                'monto_principal_centavos' => $gasto['monto_principal_centavos'] !== null
                    ? (int) $gasto['monto_principal_centavos'] : $monto,
                'moneda' => (string) $gasto['moneda'],
                'monto_total_compartido_centavos' => $gasto['monto_total_compartido_centavos'] !== null
                    ? (int) $gasto['monto_total_compartido_centavos'] : null,
                'monto_total_compartido_principal_centavos' => $gasto['monto_total_compartido_principal_centavos'] !== null
                    ? (int) $gasto['monto_total_compartido_principal_centavos'] : null,
                'parte_metodo' => $part,
                'subcategoria_id' => $gasto['subcategoria_id'] !== null ? (int) $gasto['subcategoria_id'] : null,
                'subcategoria_nombre' => $gasto['subcategoria_nombre'],
                'fecha' => (string) $gasto['fecha'],
                'nota' => $gasto['nota'],
                'foto_path' => $gasto['foto_path'],
                'medio_pago' => $gasto['medio_pago'],
                'estado' => 'asignado',
                'fuente_exceso' => $split['excedido'] ? 'no_lo_se' : 'no_aplica',
            ]);

            (new NoControladoRepository($db))->deleteByReferencia($userId, 'gasto_excedido', $gastoId);

            if ($split['excedido'] && $split['exceso_centavos'] > 0) {
                (new NoControladoRepository($db))->create(
                    $userId,
                    $inicio,
                    $fin,
                    $split['exceso_centavos'],
                    $principal,
                    'gasto_excedido',
                    $gastoId,
                    $part
                );
            }

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        return [
            'gasto_id' => $gastoId,
            'estado' => 'asignado',
            'disponible_centavos' => $available,
            'cubierto_centavos' => $split['cubierto_centavos'],
            'exceso_centavos' => $split['exceso_centavos'],
            'excedido' => $split['excedido'],
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    public function conciliarTodos(array $user): int
    {
        $pending = (new GastoRepository())->pendingForUser((int) $user['id']);
        $count = 0;

        foreach ($pending as $gasto) {
            $this->conciliar($user, (int) $gasto['id']);
            $count++;
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $gasto
     * @param array<string, mixed> $user
     */
    public function puedeEditar(array $gasto, array $user): bool
    {
        $period = $this->periodoActual($user);

        return ($gasto['estado'] ?? '') !== 'cerrado'
            && (string) $gasto['periodo_inicio'] === $period['inicio']
            && (string) $gasto['periodo_fin'] === $period['fin'];
    }

    public function sugerirMedioPago(int $userId): ?string
    {
        return (new GastoRepository())->lastPaymentMethod($userId);
    }

    /**
     * Crea una ocurrencia de gasto recurrente con período y fecha explícitos.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $datos
     * @param array{inicio: string, fin: string} $period
     * @return array<string, mixed>
     */
    public function crearRecurrente(array $user, array $datos, array $period, bool $hasIncome): array
    {
        return $this->insertar(
            $user,
            $datos,
            $period,
            (string) ($datos['fecha'] ?? gmdate('Y-m-d H:i:s')),
            null,
            [
                'estado' => $hasIncome ? 'asignado' : 'pendiente_asignacion',
                'confirmado' => 0,
                'es_recurrente' => 1,
                'gasto_recurrente_id' => isset($datos['gasto_recurrente_id']) ? (int) $datos['gasto_recurrente_id'] : null,
            ]
        );
    }

    /**
     * Compatibilidad con la preparación de Fase 3.
     *
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public function registrarGasto(int $userId, array $datos): array
    {
        $user = (new UserRepository())->findById($userId);

        if ($user === null) {
            throw new RuntimeException('El usuario no existe.');
        }

        return $this->crearManual($user, $datos, $datos['foto_path'] ?? null);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $datos
     * @param array{inicio: string, fin: string} $period
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function insertar(
        array $user,
        array $datos,
        array $period,
        string $fechaUtc,
        ?string $fotoPath,
        array $options
    ): array {
        $userId = (int) $user['id'];
        $amount = (int) ($datos['monto_centavos'] ?? 0);

        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto del gasto debe ser mayor que cero.');
        }

        if ($amount > (int) config('ingreso.max_amount_cents', 1000000000000)) {
            throw new InvalidArgumentException('El monto del gasto es demasiado alto.');
        }

        $currencyInput = trim((string) ($datos['moneda'] ?? ''));
        $currency = (new CurrencyService())->validateCode(
            $currencyInput !== '' ? $currencyInput : (string) ($user['moneda_activa'] ?? 'MXN')
        );
        $principal = (new CurrencyService())->principal($user);
        $conversion = (new CurrencyService())->toPrincipal(
            $user,
            $amount,
            $currency,
            substr($fechaUtc, 0, 10)
        );
        $principalAmount = $conversion['centavos'];

        $part = trim((string) ($datos['parte_metodo'] ?? ''));
        $category = null;

        foreach ($this->categoriasActivas($user) as $candidate) {
            $name = (string) $candidate['nombre'];
            $type = (string) ($candidate['tipo'] ?? '');
            $isDebt = str_contains(mb_strtolower($name), 'deuda');

            // Los gastos normales solo van a categorías controladas. La
            // excepción son los pagos de deuda, que pueden usar el bolsillo
            // "Deudas/Inversión" de métodos como 70/20/10.
            if ($name === $part && ($type === 'controlada' || $isDebt)) {
                $category = $candidate;
                break;
            }
        }

        if ($category === null) {
            throw new InvalidArgumentException('La parte del método no es válida.');
        }

        $subcategoryId = isset($datos['subcategoria_id']) && $datos['subcategoria_id'] !== ''
            ? (int) $datos['subcategoria_id']
            : null;
        $subcategoryName = null;

        if ($subcategoryId !== null && (string) $category['tipo'] === 'controlada') {
            $subcategory = (new SubcategoriaRepository())->findForUser($subcategoryId, $userId);

            if ($subcategory === null) {
                $subcategoryId = null;
            } else {
                $subcategoryName = mb_substr((string) $subcategory['nombre'], 0, 60);

                if (!empty($datos['recordar_parte'])) {
                    (new SubcategoriaRepository())->rememberPart($subcategoryId, $userId, $part);
                }
            }
        } else {
            $subcategoryId = null;
        }

        $sharedTotal = isset($datos['monto_total_compartido_centavos'])
            && (int) $datos['monto_total_compartido_centavos'] > 0
            ? (int) $datos['monto_total_compartido_centavos']
            : null;

        if ($sharedTotal !== null && $sharedTotal < $amount) {
            throw new InvalidArgumentException('El total compartido no puede ser menor que tu parte.');
        }

        $sharedPrincipal = $sharedTotal !== null
            ? (new CurrencyService())->toPrincipal($user, $sharedTotal, $currency, substr($fechaUtc, 0, 10))['centavos']
            : null;

        $state = (string) ($options['estado'] ?? 'asignado');
        $available = null;
        $split = ['cubierto_centavos' => $principalAmount, 'exceso_centavos' => 0, 'excedido' => false];
        $repo = new GastoRepository();

        if ($state !== 'pendiente_asignacion') {
            $assigned = $repo->sumAssignedForPart($userId, $part, $period['inicio'], $period['fin']);
            $spent = !empty($options['exclude_id'])
                ? $repo->sumSpentForPartExcluding((int) $options['exclude_id'], $userId, $part, $period['inicio'], $period['fin'])
                : $repo->sumSpentForPart($userId, $part, $period['inicio'], $period['fin']);
            $available = max(0, $assigned - $spent);
            $split = $this->calcularRepartoGasto($principalAmount, $available);
        }

        $source = (string) ($datos['fuente_exceso'] ?? 'no_aplica');

        if (!in_array($source, self::FUENTES, true)) {
            $source = 'no_aplica';
        }

        if ($split['excedido'] && $source === 'no_aplica') {
            $source = 'no_lo_se';
        }

        if (!$split['excedido']) {
            $source = 'no_aplica';
        }

        $medium = (string) ($datos['medio_pago'] ?? '');
        $medium = in_array($medium, ['efectivo', 'debito', 'credito', 'transferencia', 'otro'], true)
            ? $medium
            : null;

        $payload = [
            'periodo_inicio' => $period['inicio'],
            'periodo_fin' => $period['fin'],
            'monto_centavos' => $amount,
            'monto_principal_centavos' => $principalAmount,
            'moneda' => $currency,
            'monto_total_compartido_centavos' => $sharedTotal,
            'monto_total_compartido_principal_centavos' => $sharedPrincipal,
            'parte_metodo' => $part,
            'subcategoria_id' => $subcategoryId,
            'subcategoria_nombre' => $subcategoryName,
            'fecha' => $fechaUtc,
            'nota' => isset($datos['nota']) && trim((string) $datos['nota']) !== ''
                ? mb_substr(trim((string) $datos['nota']), 0, 2000)
                : null,
            'foto_path' => $fotoPath,
            'medio_pago' => $medium,
            'estado' => $state,
            'fuente_exceso' => $source,
            'confirmado' => (int) ($options['confirmado'] ?? 1),
            'es_recurrente' => (int) ($options['es_recurrente'] ?? 0),
            'gasto_recurrente_id' => $options['gasto_recurrente_id'] ?? null,
        ];

        $db = Database::connection();
        $ownsTransaction = !$db->inTransaction();

        try {
            if ($ownsTransaction) {
                $db->beginTransaction();
            }

            if (!empty($options['update_id'])) {
                (new GastoRepository($db))->update(
                    (int) $options['update_id'],
                    $userId,
                    $payload
                );
                $gastoId = (int) $options['update_id'];
            } else {
                $gastoId = (new GastoRepository($db))->create(array_merge($payload, ['user_id' => $userId]));
            }

            (new NoControladoRepository($db))->deleteByReferencia($userId, 'gasto_excedido', $gastoId);

            if ($split['excedido'] && $split['exceso_centavos'] > 0) {
                (new NoControladoRepository($db))->create(
                    $userId,
                    $period['inicio'],
                    $period['fin'],
                    $split['exceso_centavos'],
                    $principal,
                    'gasto_excedido',
                    $gastoId,
                    $part
                );
            }

            if (empty($options['update_id'])
                && $source === 'deuda'
                && $split['excedido']
                && $split['exceso_centavos'] > 0
            ) {
                (new DebtService())->applyExcessFromExpense($user, $datos, $split['exceso_centavos']);
            }

            (new TagService())->syncGasto($gastoId, $userId, $datos['tags'] ?? []);

            if (!empty($datos['plantilla_id'])) {
                (new PlantillaRepository($db))->touchUsage((int) $datos['plantilla_id']);
            }

            if ($ownsTransaction) {
                $db->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        return [
            'gasto_id' => $gastoId,
            'estado' => $state,
            'disponible_centavos' => $available,
            'cubierto_centavos' => $split['cubierto_centavos'],
            'exceso_centavos' => $split['exceso_centavos'],
            'excedido' => $split['excedido'],
        ];
    }

    /**
     * @param array{inicio: string, fin: string} $period
     */
    private function assertWithinPeriod(DateTimeImmutable $date, array $period): void
    {
        $start = new DateTimeImmutable($period['inicio'], new DateTimeZone('UTC'));
        $end = new DateTimeImmutable($period['fin'], new DateTimeZone('UTC'));

        if ($date < $start || $date > $end) {
            throw new RuntimeException('fuera_periodo');
        }
    }

    private function toUtc(string $value, DateTimeZone $timezone): DateTimeImmutable
    {
        $value = trim($value);

        if ($value === '' || $value === 'now') {
            return new DateTimeImmutable('now', $timezone);
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) === 1) {
            if (!checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
                throw new InvalidArgumentException('La fecha no es válida.');
            }

            $value .= ' 12:00:00';
        }

        return (new DateTimeImmutable($value, $timezone))->setTimezone(new DateTimeZone('UTC'));
    }
}
