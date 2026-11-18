<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Helpers\Logger;
use Cauce\Repositories\AsignacionRepository;
use Cauce\Repositories\IngresoRepository;
use Cauce\Repositories\JustificacionRepository;
use Cauce\Repositories\MetodoRepository;
use Cauce\Repositories\NoControladoRepository;
use Cauce\Repositories\UsuarioMetodoRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Creación y finalización de ingresos.
 *
 * El ingreso y sus asignaciones se crean al confirmar el monto, con estado
 * pendiente_asignacion. La finalización valida las decisiones y las aplica en
 * una sola transacción.
 */
final class IngresoService
{
    private RepartoService $reparto;

    public function __construct(?RepartoService $reparto = null)
    {
        $this->reparto = $reparto ?? new RepartoService();
    }

    /**
     * Reparto ordenado por metodo_categorias.orden.
     *
     * @param list<array{nombre: string, porcentaje: ?int, tipo: string, orden: int}> $categorias
     * @param array<string, int|float> $guardados
     * @return array<string, int>
     */
    public function repartoPorMetodo(array $categorias, array $guardados, int $montoCentavos): array
    {
        $porcentajes = [];

        foreach ($categorias as $categoria) {
            $nombre = (string) $categoria['nombre'];
            $valor = $guardados[$nombre] ?? $categoria['porcentaje'] ?? null;

            if ($valor === null) {
                continue;
            }

            if (!is_numeric($valor) || (float) $valor < 0) {
                throw new InvalidArgumentException('Porcentaje inválido para ' . $nombre . '.');
            }

            $porcentajes[$nombre] = (float) $valor;
        }

        if ($porcentajes === []) {
            throw new RuntimeException('El método no tiene categorías con porcentaje.');
        }

        if (abs(array_sum($porcentajes) - 100.0) > 0.01) {
            throw new RuntimeException('Los porcentajes del método no suman 100.');
        }

        return $this->reparto->repartirIngreso($montoCentavos, $porcentajes);
    }

    /**
     * Reparto del ingreso extra: regla del usuario o fallback 100 % ahorro.
     *
     * @param list<array{nombre: string, porcentaje: ?int, tipo: string, orden: int}> $categorias
     * @param array<string, int|float>|null $guardado
     * @return array<string, int>
     */
    public function repartoExtra(array $categorias, ?array $guardado, int $montoCentavos): array
    {
        if (is_array($guardado) && $guardado !== []) {
            $porcentajes = [];

            foreach ($categorias as $categoria) {
                $nombre = (string) $categoria['nombre'];

                if (array_key_exists($nombre, $guardado) && is_numeric($guardado[$nombre])) {
                    $porcentajes[$nombre] = (float) $guardado[$nombre];
                }
            }

            if ($porcentajes !== [] && abs(array_sum($porcentajes) - 100.0) <= 0.01) {
                return $this->reparto->repartirIngreso($montoCentavos, $porcentajes);
            }
        }

        $fallback = $this->categoriaExtraPorDefecto($categorias);
        $porcentajes = [$fallback => (float) config('ingreso.extra.percentage', 100)];

        return $this->reparto->repartirIngreso($montoCentavos, $porcentajes);
    }

    /**
     * @param list<array{nombre: string, porcentaje: ?int, tipo: string, orden: int}> $categorias
     */
    public function categoriaExtraPorDefecto(array $categorias): string
    {
        $types = (array) config('ingreso.extra.fallback_types', ['ahorro', 'inversion']);

        foreach ($types as $type) {
            foreach ($categorias as $categoria) {
                if (($categoria['tipo'] ?? '') === $type) {
                    return (string) $categoria['nombre'];
                }
            }
        }

        foreach ($categorias as $categoria) {
            if ($categoria['porcentaje'] !== null) {
                return (string) $categoria['nombre'];
            }
        }

        if ($categorias === []) {
            throw new RuntimeException('El método no tiene categorías.');
        }

        return (string) $categorias[0]['nombre'];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     * @return array{reparto: ?array<string, int>, errors: list<string>}
     */
    public function validarExtraReparto(array $user, array $input): array
    {
        // Solo las categorías con % en la versión vigente del método del usuario.
        $categorias = $this->categoriasActivas($user);
        $guardado = [];
        $known = array_map(static fn (array $categoria): string => (string) $categoria['nombre'], $categorias);

        foreach (array_keys($input) as $key) {
            if (!in_array((string) $key, $known, true)) {
                return ['reparto' => null, 'errors' => ['income.errors.extra_category']];
            }
        }

        foreach ($categorias as $categoria) {
            $nombre = (string) $categoria['nombre'];
            $raw = $input[$nombre] ?? null;

            if ($raw === null || $raw === '') {
                continue;
            }

            if (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > 100) {
                return ['reparto' => null, 'errors' => ['income.errors.extra_value']];
            }

            $guardado[$nombre] = (float) $raw;
        }

        if ($guardado === []) {
            return ['reparto' => null, 'errors' => []];
        }

        if (abs(array_sum($guardado) - 100.0) > 0.01) {
            return ['reparto' => null, 'errors' => ['income.errors.extra_sum']];
        }

        $reparto = [];

        foreach ($categorias as $categoria) {
            $nombre = (string) $categoria['nombre'];

            if (isset($guardado[$nombre]) && $guardado[$nombre] > 0) {
                $reparto[$nombre] = (float) $guardado[$nombre];
            }
        }

        return ['reparto' => $reparto, 'errors' => []];
    }

    /**
     * @param array<string, mixed> $user
     * @return array{ingreso_id: int, reparto: array<string, int>, metodo: array<string, mixed>}
     */
    public function crear(array $user, int $montoCentavos, string $tipo, ?string $currency = null): array
    {
        if (!in_array($tipo, (array) config('ingreso.types', []), true)) {
            throw new InvalidArgumentException('Tipo de ingreso inválido.');
        }

        if ($montoCentavos <= 0 || $montoCentavos > (int) config('ingreso.max_amount_cents', 1000000000000)) {
            throw new InvalidArgumentException('Monto inválido.');
        }

        $userId = (int) $user['id'];
        $active = (new UsuarioMetodoRepository())->activeForUser($userId);

        if ($active === null) {
            throw new RuntimeException('No hay un método activo.');
        }

        $metodo = (new MetodoRepository())->findById((int) $active['metodo_id']);

        if ($metodo === null) {
            throw new RuntimeException('El método activo no existe.');
        }

        $stored = json_decode((string) ($active['porcentajes_json'] ?? ''), true);
        $stored = is_array($stored) ? $stored : [];
        $currencyInput = trim((string) ($currency ?? ''));
        $currency = (new CurrencyService())->validateCode(
            $currencyInput !== '' ? $currencyInput : (string) ($user['moneda_activa'] ?? 'MXN')
        );
        $principal = (new CurrencyService())->principal($user);
        $conversion = (new CurrencyService())->toPrincipal($user, $montoCentavos, $currency, gmdate('Y-m-d'));
        $principalCents = $conversion['centavos'];

        $reparto = $tipo === 'extra'
            ? $this->repartoExtra(
                $this->filtrarActivas($metodo['categorias'], $stored),
                $this->extraGuardado($user),
                $principalCents
            )
            : $this->repartoPorMetodo($metodo['categorias'], $stored, $principalCents);

        $period = (new PeriodoService())->calcularPeriodo($user);
        $inicio = $period['inicio']->format('Y-m-d H:i:s');
        $fin = $period['fin']->format('Y-m-d H:i:s');
        $recurrencia = $tipo === 'extra'
            ? null
            : (string) ($user['recurrencia'] ?? config('periodo.default_recurrence', 'mensual'));

        $rows = [];

        foreach ($reparto as $categoria => $monto) {
            $rows[] = ['categoria' => $categoria, 'monto_centavos' => $monto];
        }

        $db = Database::connection();

        try {
            $db->beginTransaction();

            $ingresoId = (new IngresoRepository($db))->create([
                'user_id' => $userId,
                'metodo_id' => (int) $metodo['id'],
                'periodo_inicio' => $inicio,
                'periodo_fin' => $fin,
                'monto_centavos' => $montoCentavos,
                'monto_principal_centavos' => $principalCents,
                'moneda' => $currency,
                'tipo' => $tipo,
                'recurrencia' => $recurrencia,
                'fecha' => gmdate('Y-m-d H:i:s'),
                'nota' => null,
            ]);

            (new AsignacionRepository($db))->createMany(
                $ingresoId,
                $userId,
                $rows,
                $principal
            );

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        return [
            'ingreso_id' => $ingresoId,
            'reparto' => $reparto,
            'metodo' => $metodo,
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<int|string, array<string, mixed>> $decisiones
     */
    public function finalizar(array $user, int $ingresoId, array $decisiones): void
    {
        $userId = (int) $user['id'];
        $ingreso = (new IngresoRepository())->findForUser($ingresoId, $userId);

        if ($ingreso === null) {
            throw new RuntimeException('El ingreso no existe.');
        }

        $asignaciones = (new AsignacionRepository())->forIngreso($ingresoId);
        $pendientes = array_values(array_filter(
            $asignaciones,
            static fn (array $row): bool => ($row['estado'] ?? '') === 'pendiente_asignacion'
        ));

        if ($pendientes === []) {
            $this->afterFinalize($user, $ingreso);

            return;
        }

        $reasons = (array) config('ingreso.justification_reasons', []);
        $states = (array) config('ingreso.states', []);

        foreach ($pendientes as $asignacion) {
            $id = (int) $asignacion['id'];
            $decision = $decisiones[$id] ?? $decisiones[(string) $id] ?? null;

            if (!is_array($decision)) {
                throw new InvalidArgumentException('Falta decidir una categoría.');
            }

            $estado = (string) ($decision['estado'] ?? '');

            if (!in_array($estado, $states, true)) {
                throw new InvalidArgumentException('Estado de asignación inválido.');
            }

            if ($estado === 'justificado') {
                $motivo = (string) ($decision['motivo'] ?? '');

                if (!in_array($motivo, $reasons, true)) {
                    throw new InvalidArgumentException('Motivo de justificación inválido.');
                }
            }
        }

        $db = Database::connection();

        try {
            $db->beginTransaction();

            $asignacionesRepo = new AsignacionRepository($db);
            $justificacionesRepo = new JustificacionRepository($db);
            $noControladoRepo = new NoControladoRepository($db);

            foreach ($pendientes as $asignacion) {
                $id = (int) $asignacion['id'];
                $decision = $decisiones[$id] ?? $decisiones[(string) $id];
                $estado = (string) $decision['estado'];

                if ($estado === 'justificado') {
                    $texto = trim((string) ($decision['texto'] ?? ''));
                    $justificacionId = $justificacionesRepo->create(
                        $id,
                        (string) $decision['motivo'],
                        $texto !== '' ? mb_substr($texto, 0, 2000) : null
                    );

                    $asignacionesRepo->updateEstado($id, 'justificado', $justificacionId);
                    continue;
                }

                $asignacionesRepo->updateEstado($id, $estado);

                if ($estado === 'no_controlado') {
                    $noControladoRepo->create(
                        $userId,
                        (string) $ingreso['periodo_inicio'],
                        (string) $ingreso['periodo_fin'],
                        (int) $asignacion['monto_centavos'],
                        (string) $asignacion['moneda'],
                        'asignacion',
                        $id,
                        (string) $asignacion['categoria']
                    );
                }
            }

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        $this->afterFinalize($user, $ingreso);
    }

    /**
     * Acredita todas las categorías del ingreso de una vez.
     *
     * Consumo y las demás categorías quedan como disponible; la persona va
     * registrando gastos, aportes o donaciones contra ese disponible. En el
     * mismo paso se aplican las decisiones sobre los gastos recurrentes del
     * período y los abonos opcionales a deudas, metas, bolsas y deseos.
     *
     * @param array<string, mixed> $user
     * @param array<int|string, string> $recurrentes  id de recurrente => aceptar|no_controlado
     * @param array<int|string, string> $gastosRecurrentes  id de gasto pendiente => aceptar|no_controlado
     * @param array{deuda?: array<int|string, int>, meta?: array<int|string, int>, bolsa?: array<int|string, int>, deseo?: array<int|string, int>, deuda_categoria?: string} $abonos
     * @return array{abonos: int, errores: list<string>}
     */
    public function acreditarTodo(
        array $user,
        int $ingresoId,
        array $recurrentes = [],
        array $gastosRecurrentes = [],
        array $abonos = []
    ): array {
        $userId = (int) $user['id'];
        $ingreso = (new IngresoRepository())->findForUser($ingresoId, $userId);

        if ($ingreso === null) {
            throw new RuntimeException('El ingreso no existe.');
        }

        $db = Database::connection();

        try {
            $db->beginTransaction();
            $asignaciones = new AsignacionRepository($db);

            foreach ($asignaciones->forIngreso($ingresoId) as $asignacion) {
                if (($asignacion['estado'] ?? '') === 'pendiente_asignacion') {
                    $asignaciones->updateEstado((int) $asignacion['id'], 'acreditado');
                }
            }

            $recurring = new \Cauce\Repositories\RecurringExpenseRepository($db);

            foreach ($recurrentes as $id => $decision) {
                $row = $recurring->findForUser((int) $id, $userId);

                if ($row === null) {
                    continue;
                }

                $recurring->setNoControladoHasta(
                    (int) $id,
                    $userId,
                    $decision === 'no_controlado' ? (string) $ingreso['periodo_fin'] : null
                );
            }

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        $gastos = new GastoService();
        $repoGastos = new \Cauce\Repositories\GastoRepository();

        foreach ($gastosRecurrentes as $id => $decision) {
            $gasto = $repoGastos->findForUser((int) $id, $userId);

            if ($gasto === null || empty($gasto['gasto_recurrente_id'])
                || (string) $gasto['periodo_inicio'] !== (string) $ingreso['periodo_inicio']) {
                continue;
            }

            if ($decision === 'no_controlado') {
                $gastos->marcarNoControlado($user, (int) $id);
            } elseif (($gasto['estado'] ?? '') === 'pendiente_asignacion') {
                $gastos->conciliar($user, (int) $id);
            }
        }

        $this->afterFinalize($user, $ingreso);

        return $this->aplicarAbonos($user, $abonos);
    }

    /**
     * Corrige el monto de un ingreso del período actual y recalcula su
     * reparto con el método vigente (o la regla de extras).
     *
     * Si lo ya usado en el período deja de caber en el nuevo monto, lanza
     * `income.errors.used_exceeds` hasta que la persona confirme.
     *
     * @param array<string, mixed> $user
     */
    public function actualizarMonto(array $user, int $ingresoId, int $montoCentavos, bool $confirmado = false): void
    {
        if ($montoCentavos <= 0 || $montoCentavos > (int) config('ingreso.max_amount_cents', 1000000000000)) {
            throw new InvalidArgumentException('Monto inválido.');
        }

        $userId = (int) $user['id'];
        $ingreso = $this->ingresoEditable($user, $ingresoId);
        $currency = (string) $ingreso['moneda'];
        $principalCents = (new CurrencyService())->toPrincipal(
            $user,
            $montoCentavos,
            $currency,
            substr((string) $ingreso['fecha'], 0, 10)
        )['centavos'];

        $categorias = $this->categoriasDelMetodoActivo($userId);
        $active = (new UsuarioMetodoRepository())->activeForUser($userId);
        $stored = json_decode((string) ($active['porcentajes_json'] ?? ''), true);
        $stored = is_array($stored) ? $stored : [];

        if ($categorias === []) {
            throw new RuntimeException('No hay un método activo.');
        }

        $reparto = (string) $ingreso['tipo'] === 'extra'
            ? $this->repartoExtra($this->filtrarActivas($categorias, $stored), $this->extraGuardado($user), $principalCents)
            : $this->repartoPorMetodo($categorias, $stored, $principalCents);

        $asignaciones = (new AsignacionRepository())->forIngreso($ingresoId);
        $credited = $this->sumaAcreditada($asignaciones);
        $wasCredited = $credited > 0
            || array_filter($asignaciones, static fn (array $row): bool => ($row['estado'] ?? '') !== 'pendiente_asignacion') !== [];

        if ($wasCredited && !$confirmado) {
            $this->assertCabe($user, $credited, $principalCents);
        }

        $db = Database::connection();

        try {
            $db->beginTransaction();
            $this->limpiarNoControlado($db, $userId, $asignaciones);
            (new IngresoRepository($db))->updateMonto($ingresoId, $userId, $montoCentavos, $principalCents, $currency);
            $repo = new AsignacionRepository($db);
            $repo->deleteForIngreso($ingresoId, $userId);
            $rows = [];

            foreach ($reparto as $categoria => $monto) {
                $rows[] = ['categoria' => $categoria, 'monto_centavos' => $monto];
            }

            $repo->createMany($ingresoId, $userId, $rows, (new CurrencyService())->principal($user));

            if ($wasCredited) {
                foreach ($repo->forIngreso($ingresoId) as $row) {
                    $repo->updateEstado((int) $row['id'], 'acreditado');
                }
            }

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Elimina un ingreso del período actual con su reparto.
     *
     * Con $borrarMovimientos también se borra todo lo que salió de ese dinero
     * en el período (gastos, aportes, donaciones, Compartir, pagos de deuda y
     * movimientos entre categorías), deshaciendo sus efectos.
     *
     * @param array<string, mixed> $user
     * @return int movimientos borrados
     */
    public function eliminar(array $user, int $ingresoId, bool $confirmado = false, bool $borrarMovimientos = false): int
    {
        $userId = (int) $user['id'];
        $ingreso = $this->ingresoEditable($user, $ingresoId);
        $asignaciones = (new AsignacionRepository())->forIngreso($ingresoId);

        if (!$confirmado && !$borrarMovimientos) {
            $this->assertCabe($user, $this->sumaAcreditada($asignaciones), 0);
        }

        $db = Database::connection();
        $deleted = 0;

        try {
            $db->beginTransaction();

            if ($borrarMovimientos) {
                $deleted = (new MovementReversalService($db))->deleteForPeriod(
                    $userId,
                    (string) $ingreso['periodo_inicio'],
                    (string) $ingreso['periodo_fin']
                );
            }

            $this->limpiarNoControlado($db, $userId, $asignaciones);
            (new IngresoRepository($db))->delete($ingresoId, $userId);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        return $deleted;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function ingresoEditable(array $user, int $ingresoId): array
    {
        $ingreso = (new IngresoRepository())->findForUser($ingresoId, (int) $user['id']);

        if ($ingreso === null) {
            throw new RuntimeException('El ingreso no existe.');
        }

        $period = (new PeriodoService())->calcularPeriodo($user);

        if ((string) $ingreso['periodo_inicio'] !== $period['inicio']->format('Y-m-d H:i:s')
            || (string) $ingreso['periodo_fin'] !== $period['fin']->format('Y-m-d H:i:s')) {
            throw new RuntimeException('income.errors.closed_period');
        }

        return $ingreso;
    }

    /**
     * ¿Lo ya usado en el período sigue cabiendo si el ingreso pasa de
     * $antes a $despues (montos acreditados, en moneda principal)?
     *
     * @param array<string, mixed> $user
     */
    private function assertCabe(array $user, int $antes, int $despues): void
    {
        if ($despues >= $antes) {
            return;
        }

        $gastos = new GastoService();
        $disponible = $gastos->disponibleTotal($gastos->disponiblePorParte($user));

        if ($disponible - $antes + $despues < 0) {
            throw new RuntimeException('income.errors.used_exceeds');
        }
    }

    /**
     * @param list<array<string, mixed>> $asignaciones
     */
    private function sumaAcreditada(array $asignaciones): int
    {
        $total = 0;

        foreach ($asignaciones as $row) {
            if (in_array((string) ($row['estado'] ?? ''), ['acreditado', 'justificado'], true)) {
                $total += (int) ($row['monto_principal_centavos'] ?? $row['monto_centavos']);
            }
        }

        return $total;
    }

    /**
     * @param list<array<string, mixed>> $asignaciones
     */
    private function limpiarNoControlado(\PDO $db, int $userId, array $asignaciones): void
    {
        $repo = new NoControladoRepository($db);

        foreach ($asignaciones as $row) {
            $repo->deleteByReferencia($userId, 'asignacion', (int) $row['id']);
        }
    }

    /**
     * Abonos opcionales desde la pantalla de acreditar. Cada uno se registra
     * por separado con su propio servicio; si uno falla, los demás siguen.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $abonos
     * @return array{abonos: int, errores: list<string>}
     */
    public function aplicarAbonos(array $user, array $abonos): array
    {
        $done = 0;
        $errors = [];
        $category = trim((string) ($abonos['deuda_categoria'] ?? ''));
        $userId = (int) $user['id'];

        $run = static function (string $label, callable $action) use (&$done, &$errors): void {
            try {
                $action();
                $done++;
            } catch (Throwable $exception) {
                Logger::warning('No se pudo registrar un abono al acreditar', ['error' => $exception->getMessage()]);
                $errors[] = $label;
            }
        };

        foreach ($this->montos($abonos['deuda'] ?? []) as $id => $amount) {
            $debt = (new \Cauce\Repositories\DebtRepository())->findForUser($id, $userId);
            $run((string) ($debt['nombre'] ?? '#' . $id), static function () use ($user, $id, $amount, $category, $debt): void {
                $debtService = new DebtService();
                $allowed = array_column($debtService->paymentCategories($user), 'nombre');
                $part = in_array($category, $allowed, true) ? $category : (string) ($allowed[0] ?? '');
                $debtService->pay(
                    $user,
                    $id,
                    min($amount, (int) ($debt['saldo_actual_centavos'] ?? $amount)),
                    'now',
                    null,
                    $part !== '' ? 'ingreso' : 'otro',
                    $part !== '' ? $part : null
                );
            });
        }

        foreach ($this->montos($abonos['meta'] ?? []) as $id => $amount) {
            $meta = (new \Cauce\Repositories\SavingsRepository())->findMeta($id, $userId);
            $run((string) ($meta['nombre'] ?? '#' . $id), static function () use ($user, $id, $amount): void {
                (new SavingsService())->contribute($user, null, $id, $amount, 'now');
            });
        }

        foreach ($this->montos($abonos['bolsa'] ?? []) as $id => $amount) {
            $account = (new \Cauce\Repositories\SavingsRepository())->findAccount($id, $userId);
            $run((string) ($account['nombre'] ?? '#' . $id), static function () use ($user, $id, $amount): void {
                (new SavingsService())->contribute($user, $id, null, $amount, 'now');
            });
        }

        foreach ($this->montos($abonos['deseo'] ?? []) as $id => $amount) {
            $wish = (new \Cauce\Repositories\WishlistRepository())->findForUser($id, $userId);
            $run((string) ($wish['nombre'] ?? '#' . $id), static function () use ($user, $id, $amount): void {
                (new WishlistService())->contribute($user, $id, $amount, 'now');
            });
        }

        return ['abonos' => $done, 'errores' => $errors];
    }

    /**
     * @param mixed $values
     * @return array<int, int>
     */
    private function montos(mixed $values): array
    {
        $result = [];

        foreach (is_array($values) ? $values : [] as $id => $amount) {
            if ((int) $id > 0 && is_int($amount) && $amount > 0) {
                $result[(int) $id] = $amount;
            }
        }

        return $result;
    }

    /**
     * Actualiza la racha después de confirmar un ingreso, sin romper el flujo
     * principal si la gamificación falla.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $ingreso
     */
    private function afterFinalize(array $user, array $ingreso): void
    {
        try {
            (new GamificationService())->onIncomeFinalized($user, $ingreso);
        } catch (Throwable $exception) {
            Logger::warning('No se pudo actualizar la racha', [
                'user_id' => $user['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, int|float>|null
     */
    public function extraGuardado(array $user): ?array
    {
        $raw = $user['extra_reparto_json'] ?? null;

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Categorías del método vigente que reciben dinero (con % > 0 en la
     * versión del usuario), con ese % en 'porcentaje_usuario'. Son las que
     * reparten los ingresos fijos y variables.
     *
     * @param array<string, mixed> $user
     * @return list<array{nombre: string, porcentaje: ?int, tipo: string, orden: int, porcentaje_usuario: float}>
     */
    public function categoriasActivas(array $user): array
    {
        $active = (new UsuarioMetodoRepository())->activeForUser((int) $user['id']);

        if ($active === null) {
            return [];
        }

        $metodo = (new MetodoRepository())->findById((int) $active['metodo_id']);

        if ($metodo === null) {
            return [];
        }

        $stored = json_decode((string) ($active['porcentajes_json'] ?? ''), true);

        return $this->filtrarActivas($metodo['categorias'], is_array($stored) ? $stored : []);
    }

    /**
     * @param list<array{nombre: string, porcentaje: ?int, tipo: string, orden: int}> $categorias
     * @param array<string, int|float> $guardados
     * @return list<array{nombre: string, porcentaje: ?int, tipo: string, orden: int, porcentaje_usuario: float}>
     */
    public function filtrarActivas(array $categorias, array $guardados): array
    {
        $result = [];

        foreach ($categorias as $categoria) {
            $nombre = (string) $categoria['nombre'];
            $valor = $guardados[$nombre] ?? $categoria['porcentaje'] ?? null;

            if (!is_numeric($valor) || (float) $valor <= 0.001) {
                continue;
            }

            $categoria['porcentaje_usuario'] = (float) $valor;
            $result[] = $categoria;
        }

        return $result;
    }

    /**
     * @return list<array{nombre: string, porcentaje: ?int, tipo: string, orden: int}>
     */
    public function categoriasDelMetodoActivo(int $userId): array
    {
        $active = (new UsuarioMetodoRepository())->activeForUser($userId);

        if ($active === null) {
            return [];
        }

        $metodo = (new MetodoRepository())->findById((int) $active['metodo_id']);

        return $metodo !== null ? $metodo['categorias'] : [];
    }
}
