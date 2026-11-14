<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Repositories\MetodoRepository;
use Cauce\Repositories\UsuarioMetodoRepository;
use Cauce\Repositories\UserRepository;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Estado y finalización del onboarding.
 *
 * El progreso vive en users.onboarding_estado (JSON) y se fusiona solo con
 * claves conocidas: el cliente nunca puede escribir el JSON completo.
 */
final class OnboardingService
{
    private const STATE_KEYS = [
        'version',
        'paso',
        'tutorial_visto',
        'monto_centavos',
        'metodo_preview',
        'quiz',
        'metodo',
        'moneda',
        'tipo_ingreso',
        'configuracion',
        'recurrencia',
        'primer_ingreso',
        'notificaciones',
        'completed_at',
    ];

    private ?UserRepository $users;

    public function __construct(?UserRepository $users = null)
    {
        $this->users = $users;
    }

    /**
     * @return list<string>
     */
    public function steps(): array
    {
        $steps = config('onboarding.steps', []);

        return is_array($steps) ? array_values(array_filter($steps, 'is_string')) : [];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function state(array $user): array
    {
        $defaults = [
            'version' => 1,
            'paso' => 'tutorial',
            'tutorial_visto' => false,
            'monto_centavos' => (int) config('onboarding.default_income_cents', 1000000),
            'metodo_preview' => (string) (config('onboarding.simulator_methods', ['50_30_20'])[0] ?? '50_30_20'),
            'quiz' => [],
            'metodo' => null,
            'moneda' => (string) config('onboarding.defaults.moneda', 'MXN'),
            'tipo_ingreso' => (string) config('onboarding.defaults.tipo_ingreso', 'fijo'),
            'configuracion' => (string) config('onboarding.defaults.configuracion', 'completa'),
            'recurrencia' => (string) config('onboarding.defaults.recurrencia', 'mensual'),
            'primer_ingreso' => null,
            'notificaciones' => null,
            'completed_at' => null,
        ];

        $raw = $user['onboarding_estado'] ?? null;
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        if (!is_array($decoded)) {
            return $defaults;
        }

        return $this->normalize(array_merge($defaults, $decoded));
    }

    /**
     * @param array<string, mixed> $user
     */
    public function isComplete(array $user): bool
    {
        return (int) ($user['onboarding_completado'] ?? 0) === 1;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $patch
     * @return array<string, mixed>
     */
    public function save(array $user, array $patch, ?bool $completed = null): array
    {
        $state = $this->merge($this->state($user), $patch);
        // Un autosave no debe desmarcar un onboarding ya completado.
        $completed ??= $this->isComplete($user);
        $this->users()->updateOnboarding((int) $user['id'], $state, $completed);

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $patch
     * @return array<string, mixed>
     */
    public function merge(array $state, array $patch): array
    {
        $next = $state;

        foreach (self::STATE_KEYS as $key) {
            if (array_key_exists($key, $patch)) {
                $next[$key] = $patch[$key];
            }
        }

        return $this->normalize($next);
    }

    /**
     * @param array<string, mixed> $state
     */
    public function nextStep(string $current, array $state): string
    {
        $steps = $this->steps();
        $index = array_search($current, $steps, true);

        if ($index === false) {
            return $steps[0] ?? 'tutorial';
        }

        if ($current === 'configuracion' && ($state['configuracion'] ?? 'completa') === 'rapida') {
            return 'primer-ingreso';
        }

        return $steps[$index + 1] ?? 'completado';
    }

    public function stepIndex(string $step): int
    {
        $index = array_search($step, $this->steps(), true);

        return $index === false ? 0 : (int) $index;
    }

    public function stepRoute(string $step): string
    {
        return $step === 'completado' ? '/inicio' : '/onboarding/' . $step;
    }

    /**
     * Activa el método y el perfil del onboarding antes de terminar el flujo.
     *
     * Se usa cuando la persona decide registrar su primer ingreso durante el
     * onboarding: el método debe existir antes de abrir /ingresos/nuevo, pero
     * el onboarding sigue pendiente hasta confirmar las notificaciones.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $state
     */
    public function provision(array $user, array $state): void
    {
        $db = Database::connection();

        try {
            $db->beginTransaction();
            $this->applyProfile($db, $user, $state);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $state
     */
    public function complete(array $user, array $state): void
    {
        $completedState = $this->merge($state, [
            'paso' => 'completado',
            'completed_at' => gmdate('c'),
        ]);

        $db = Database::connection();

        try {
            $db->beginTransaction();

            $this->applyProfile($db, $user, $completedState);

            (new UserRepository($db))->updateOnboarding(
                (int) $user['id'],
                $completedState,
                true
            );

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $state
     */
    private function applyProfile(PDO $db, array $user, array $state): void
    {
        $metodoCode = (string) ($state['metodo'] ?? '');

        if ($metodoCode === '') {
            throw new RuntimeException('No hay un método seleccionado.');
        }

        $method = (new MetodoRepository($db))->findByCodigo($metodoCode);

        if ($method === null) {
            throw new RuntimeException('El método seleccionado no existe.');
        }

        $porcentajes = [];

        foreach ($method['categorias'] as $categoria) {
            if ($categoria['porcentaje'] !== null) {
                $porcentajes[(string) $categoria['nombre']] = (int) $categoria['porcentaje'];
            }
        }

        if ($porcentajes === [] || abs(array_sum($porcentajes) - 100) > 0) {
            throw new RuntimeException('El método no tiene un reparto de porcentajes válido.');
        }

        $active = (new UsuarioMetodoRepository($db))->activeForUser((int) $user['id']);

        if ($active === null || (int) $active['metodo_id'] !== (int) $method['id']) {
            (new UsuarioMetodoRepository($db))->activate(
                (int) $user['id'],
                (int) $method['id'],
                $porcentajes
            );
        }

        (new SubcategoryService())->reconcileParts((int) $user['id'], $method['categorias']);

        (new UserRepository($db))->updateFromOnboarding(
            (int) $user['id'],
            (string) ($state['moneda'] ?? 'MXN'),
            (string) ($state['tipo_ingreso'] ?? 'fijo'),
            $state['recurrencia'] !== null ? (string) $state['recurrencia'] : null
        );
    }

    private function users(): UserRepository
    {
        return $this->users ??= new UserRepository();
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function normalize(array $state): array    {
        $steps = $this->steps();
        $state['version'] = 1;

        if (!in_array($state['paso'] ?? '', $steps, true) && ($state['paso'] ?? '') !== 'completado') {
            $state['paso'] = 'tutorial';
        }

        $state['tutorial_visto'] = (bool) ($state['tutorial_visto'] ?? false);

        $max = (int) config('onboarding.max_income_cents', 1000000000000);
        $state['monto_centavos'] = max(0, min($max, (int) ($state['monto_centavos'] ?? 0)));

        $state['metodo_preview'] = mb_substr((string) ($state['metodo_preview'] ?? ''), 0, 40);
        $state['metodo'] = $state['metodo'] !== null ? mb_substr((string) $state['metodo'], 0, 40) : null;

        $state['moneda'] = strtoupper(mb_substr((string) ($state['moneda'] ?? 'MXN'), 0, 3));

        if (!in_array($state['moneda'], (array) config('onboarding.currencies', []), true)) {
            $state['moneda'] = (string) config('onboarding.defaults.moneda', 'MXN');
        }

        if (!in_array($state['tipo_ingreso'] ?? '', (array) config('onboarding.income_types', []), true)) {
            $state['tipo_ingreso'] = (string) config('onboarding.defaults.tipo_ingreso', 'fijo');
        }

        if (!in_array($state['configuracion'] ?? '', (array) config('onboarding.configurations', []), true)) {
            $state['configuracion'] = (string) config('onboarding.defaults.configuracion', 'completa');
        }

        if (!in_array($state['recurrencia'] ?? '', (array) config('onboarding.recurrences', []), true)) {
            $state['recurrencia'] = null;
        }

        $quiz = $state['quiz'] ?? [];

        if (!is_array($quiz)) {
            $quiz = [];
        }

        $state['quiz'] = [
            'p1' => mb_substr((string) ($quiz['p1'] ?? ''), 0, 20),
            'p2' => mb_substr((string) ($quiz['p2'] ?? ''), 0, 20),
            'p3' => mb_substr((string) ($quiz['p3'] ?? ''), 0, 20),
        ];

        foreach (['primer_ingreso', 'notificaciones'] as $key) {
            $value = $state[$key] ?? null;
            $state[$key] = is_string($value) ? mb_substr($value, 0, 20) : null;
        }

        $state['completed_at'] = $state['completed_at'] !== null
            ? mb_substr((string) $state['completed_at'], 0, 40)
            : null;

        return $state;
    }
}
