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
        $categorias = $this->categoriasDelMetodoActivo((int) $user['id']);
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
            ? $this->repartoExtra($metodo['categorias'], $this->extraGuardado($user), $principalCents)
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
