<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Config\Database;
use Cauce\Helpers\Flash;
use Cauce\Helpers\Logger;
use Cauce\Helpers\PasswordHasher;
use Cauce\Helpers\View;
use Cauce\Repositories\RecoveryRepository;
use Cauce\Repositories\SessionRepository;
use Cauce\Repositories\UserRepository;
use Cauce\Services\AnalyticsService;
use Cauce\Services\AuthService;
use Cauce\Services\GamificationService;
use Cauce\Services\IngresoService;
use Cauce\Services\MethodService;
use Cauce\Services\PeriodoService;
use Cauce\Services\PinService;
use Cauce\Services\RecoveryService;
use Cauce\Services\ThemeService;
use Throwable;

final class SettingsController
{
    public function security(): void
    {
        $user = AuthService::user();

        if ($user === null) {
            redirect('/login');
        }

        $this->renderSecurity((int) $user['id'], [], []);
    }

    public function updateQuestions(): void
    {
        $user = AuthService::user();

        if ($user === null) {
            redirect('/login');
        }

        $errors = [];
        $currentPassword = (string) input('current_password', '');
        $questions = $this->questionsFromPost();

        if ($currentPassword === '' || !PasswordHasher::verify($currentPassword, (string) $user['password_hash'])) {
            $errors[] = __('auth.errors.current_password');
        }

        if (count($questions) !== 3) {
            $errors[] = __('auth.errors.questions_required');
        }

        foreach ($questions as $question) {
            if ($question['pregunta'] === '' || $question['respuesta'] === '') {
                $errors[] = __('auth.errors.questions_required');
                break;
            }

            if (mb_strlen($question['pregunta']) > 255 || mb_strlen($question['respuesta']) > 255) {
                $errors[] = __('common.validation.max', [':field' => __('common.fields.question'), ':max' => '255']);
                break;
            }
        }

        if ($errors !== []) {
            $this->renderSecurity((int) $user['id'], $errors, $questions);
            return;
        }

        $db = Database::connection();

        try {
            $db->beginTransaction();

            $recovery = new RecoveryService();

            (new UserRepository($db))->replaceQuestions((int) $user['id'], array_map(
                static fn (array $question): array => [
                    'pregunta' => $question['pregunta'],
                    'respuesta_hash' => PasswordHasher::hash($recovery->normalizeAnswer($question['respuesta'])),
                ],
                $questions
            ));

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            Logger::error('No se pudieron actualizar las preguntas de recuperación', [
                'error' => $exception->getMessage(),
            ]);

            $this->renderSecurity((int) $user['id'], [__('auth.errors.generic')], $questions);
            return;
        }

        Flash::success('auth.flash.questions_updated');
        redirect('/ajustes/seguridad');
    }

    public function closeSessions(): void
    {
        $user = AuthService::user();

        if ($user === null) {
            redirect('/login');
        }

        AuthService::logoutEverywhere((int) $user['id']);

        \Cauce\Helpers\Session::start();
        Flash::success('common.flash.sessions_closed');

        redirect('/login');
    }

    public function updateCash(): void
    {
        $user = $this->user();
        $raw = trim((string) input('efectivo', ''));

        try {
            $cents = $raw === '' ? null : parse_money_cents($raw);

            if ($raw !== '' && $cents === null) {
                throw new \InvalidArgumentException('Monto inválido.');
            }

            (new \Cauce\Services\NetWorthService())->updateCash($user, $cents);
            AuthService::refresh();
            Flash::success('reports.cash.saved');
        } catch (Throwable $exception) {
            Logger::warning('No se pudo actualizar el efectivo manual', [
                'user_id' => $user['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
            Flash::error('reports.cash.error');
        }

        redirect('/ajustes/perfil');
    }

    public function setPin(): void
    {
        $user = $this->user();
        $current = (string) input('current_password', '');
        $pin = (string) input('pin', '');
        $confirmation = (string) input('pin_confirmation', '');
        $errors = [];

        if (!AuthService::verifyPassword($user, $current)) {
            $errors[] = __('auth.errors.current_password');
        }

        $length = (int) config('security.pin.length', 6);

        if (preg_match('/^\d{' . $length . '}$/', $pin) !== 1) {
            $errors[] = __('auth.errors.pin_format', [':length' => (string) $length]);
        } elseif (!hash_equals($pin, $confirmation)) {
            $errors[] = __('auth.errors.pin_mismatch');
        }

        if ($errors !== []) {
            $this->renderSecurity((int) $user['id'], $errors, null);

            return;
        }

        try {
            (new PinService())->set($user, $pin);
            AuthService::refresh();
            (new AnalyticsService())->track('pin_set', [], $user);
            Flash::success('auth.flash.pin_saved');
        } catch (Throwable $exception) {
            Logger::error('No se pudo guardar el PIN', [
                'user_id' => $user['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
            Flash::error('auth.errors.generic');
        }

        redirect('/ajustes/seguridad');
    }

    public function removePin(): void
    {
        $user = $this->user();
        $current = (string) input('current_password', '');

        if (!AuthService::verifyPassword($user, $current)) {
            $this->renderSecurity((int) $user['id'], [__('auth.errors.current_password')], null);

            return;
        }

        try {
            (new PinService())->remove($user);
            AuthService::refresh();
            (new AnalyticsService())->track('pin_removed', [], $user);
            Flash::success('auth.flash.pin_removed');
        } catch (Throwable $exception) {
            Logger::error('No se pudo eliminar el PIN', [
                'user_id' => $user['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
            Flash::error('auth.errors.generic');
        }

        redirect('/ajustes/seguridad');
    }

    public function index(): void
    {
        $this->user();

        echo View::render('ajustes/index', [
            'title' => __('settings.index.title'),
        ], 'layouts/app');
    }

    public function perfil(): void
    {
        $user = $this->user();

        echo View::render('ajustes/perfil', [
            'title' => __('settings.perfil.title'),
            'user' => $user,
            'gamification' => (new GamificationService())->panel($user),
        ], 'layouts/app');
    }

    public function temas(): void
    {
        $user = $this->user();
        $service = new ThemeService();
        $theme = $service->resolve($user);

        echo View::render('ajustes/temas', [
            'title' => __('settings.temas.title'),
            'theme' => $theme,
            'derived' => $service->derive($theme['color']),
            'palette' => $service->palette(),
            'errors' => [],
        ], 'layouts/app');
    }

    public function updateTemas(): void
    {
        $user = $this->user();
        $service = new ThemeService();

        if (input('reset') === '1') {
            $theme = $service->defaults();
            $errors = [];
        } else {
            $input = [
                'modo' => input('modo', '') ?? '',
                'color' => input('color', '') ?? '',
                'color_custom' => input('color_custom', '') ?? '',
                'enfoque' => input('enfoque', '') ?? '',
                'fondo' => input('fondo', '') ?? '',
                'textura' => input('textura', '') ?? '',
                'opacidad' => input('opacidad', '') ?? '',
            ];

            if (($input['color'] ?? '') === 'custom') {
                $input['color'] = $input['color_custom'] ?? '';
            }

            $result = $service->validate($input);
            $theme = $result['theme'];
            $errors = array_map(
                static fn (array $error): string => __($error['key'], $error['params']),
                $result['errors']
            );
        }

        if ($errors !== []) {
            echo View::render('ajustes/temas', [
                'title' => __('settings.temas.title'),
                'theme' => $theme,
                'derived' => $service->derive($theme['color']),
                'palette' => $service->palette(),
                'errors' => $errors,
            ], 'layouts/app');

            return;
        }

        (new UserRepository())->updateTheme((int) $user['id'], $theme);
        AuthService::refresh();

        Flash::success('settings.temas.saved');
        redirect('/ajustes/temas');
    }

    public function compartir(): void
    {
        $this->user();

        echo View::render('ajustes/compartir', [
            'title' => __('settings.compartir.title'),
            'shareUrl' => (string) config('app.share_url', ''),
        ], 'layouts/app');
    }

    public function android(): void
    {
        $this->user();

        echo View::render('ajustes/android', [
            'title' => __('settings.android.title'),
            'version' => (string) config('app.version', '0.0.0'),
        ], 'layouts/app');
    }

    public function ingreso(): void
    {
        $user = $this->user();
        $this->renderIngreso($user);
    }

    public function updateRecurrencia(): void
    {
        $user = $this->user();
        $recurrence = (string) input('recurrencia', '');
        $day = (int) input('periodo_inicio_dia', '1');
        $errors = [];

        if (!in_array($recurrence, ['diaria', 'semanal', 'mensual', 'anual'], true)) {
            $errors[] = __('income.errors.recurrence');
        }

        if ($day < 1 || $day > 31) {
            $errors[] = __('income.errors.start_day');
        }

        if ($errors !== []) {
            $this->renderIngreso($user, $errors, [
                'recurrencia' => $recurrence,
                'periodo_inicio_dia' => $day,
            ]);
            return;
        }

        $periodo = new PeriodoService();
        $effective = $periodo->recurrenciaEfectiva($user);
        $users = new UserRepository();

        if ($recurrence !== $effective) {
            $plan = $periodo->planificarCambioRecurrencia($user, $recurrence);
            $users->updateRecurrence(
                (int) $user['id'],
                $plan['recurrencia'],
                $plan['recurrencia_anterior'],
                $plan['recurrencia_cambio_desde']
            );
            $users->updatePeriodoInicioDia((int) $user['id'], $day);

            $change = (new \DateTimeImmutable($plan['recurrencia_cambio_desde'], new \DateTimeZone('UTC')))
                ->setTimezone($periodo->userTimezone($user));

            Flash::success('settings.ingreso.recurrence_saved', [
                ':date' => format_datetime($change),
            ]);
        } else {
            // Elegir la recurrencia que ya está vigente cancela cualquier
            // cambio programado para el siguiente período.
            $users->updateRecurrence((int) $user['id'], $effective, null, null);
            $users->updatePeriodoInicioDia((int) $user['id'], $day);
            Flash::success('settings.ingreso.saved');
        }

        redirect('/ajustes/ingreso');
    }

    public function updateExtra(): void
    {
        $user = $this->user();
        $input = $_POST['reparto'] ?? [];
        $input = is_array($input) ? $input : [];
        $result = (new IngresoService())->validarExtraReparto($user, $input);
        $errors = array_map(static fn (string $key): string => __($key), $result['errors']);

        if ($errors !== []) {
            $this->renderIngreso($user, $errors, [
                'recurrencia' => (string) ($user['recurrencia'] ?? 'mensual'),
                'periodo_inicio_dia' => (int) ($user['periodo_inicio_dia'] ?? 1),
                'extra' => $input,
            ]);
            return;
        }

        $reparto = null;

        if ($result['reparto'] !== null) {
            $reparto = [];

            foreach ($result['reparto'] as $name => $percentage) {
                $reparto[(string) $name] = (int) $percentage;
            }
        }

        (new UserRepository())->updateExtraReparto((int) $user['id'], $reparto);
        Flash::success('settings.ingreso.extra_saved');
        redirect('/ajustes/ingreso');
    }

    /**
     * @param list<string> $errors
     * @param array<string, mixed>|null $old
     */
    private function renderIngreso(array $user, array $errors = [], ?array $old = null): void
    {
        $service = new IngresoService();
        // Solo las categorías que tu método vigente usa (con % > 0).
        $categories = $service->categoriasActivas($user);
        $defaultName = $categories !== [] ? $service->categoriaExtraPorDefecto($categories) : null;
        $methodData = (new MethodService())->activeData($user);

        $extra = $old['extra'] ?? null;
        $extra = is_array($extra) ? $extra : ($service->extraGuardado($user) ?? []);
        $extra = array_filter(
            $extra,
            static fn (mixed $value, string $name): bool => (float) $value > 0
                && in_array($name, array_column($categories, 'nombre'), true),
            ARRAY_FILTER_USE_BOTH
        );

        if ($extra === [] && $defaultName !== null) {
            $extra = [$defaultName => 100];
        }

        echo View::render('ajustes/ingreso', [
            'title' => __('settings.ingreso.title'),
            'recurrence' => (string) ($old['recurrencia'] ?? $user['recurrencia'] ?? 'mensual'),
            'startDay' => (int) ($old['periodo_inicio_dia'] ?? $user['periodo_inicio_dia'] ?? 1),
            'period' => (new PeriodoService())->calcularPeriodo($user),
            'changeFrom' => $user['recurrencia_cambio_desde'] ?? null,
            'recurrences' => ['diaria', 'semanal', 'mensual', 'anual'],
            'categories' => $categories,
            'methodCode' => $methodData['method']['codigo'] ?? null,
            'methodName' => $methodData['method']['nombre'] ?? null,
            'extra' => $extra,
            'errors' => $errors,
        ], 'layouts/app');
    }

    /**
     * @return array<string, mixed>
     */
    private function user(): array
    {
        $user = AuthService::user();

        if ($user === null) {
            redirect('/login');
        }

        return $user;
    }

    /**
     * @param list<string>                                             $errors
     * @param list<array{pregunta: string, respuesta: string}>|null    $submitted
     */
    private function renderSecurity(int $userId, array $errors, ?array $submitted): void
    {
        $questions = $submitted;

        if ($questions === null) {
            $existing = (new RecoveryRepository())->questionsForUser($userId);
            $defaults = lang_array('auth.register.default_questions');
            $questions = [];

            for ($i = 0; $i < 3; $i++) {
                $questions[] = [
                    'pregunta' => (string) ($existing[$i]['pregunta'] ?? $defaults[$i] ?? ''),
                    'respuesta' => '',
                ];
            }
        }

        $current = (new UserRepository())->findById($userId);
        $pin = new PinService();
        $sessions = (new SessionRepository())->forUser($userId);

        echo View::render('ajustes/seguridad', [
            'title' => __('auth.security.title'),
            'questions' => $questions,
            'errors' => $errors,
            'sessionCount' => count($sessions),
            'sessions' => $sessions,
            'hasPin' => $current !== null && $pin->hasPin($current),
            'pinLockedUntil' => $current !== null ? $pin->lockedUntil($current) : null,
            'pinLength' => (int) config('security.pin.length', 6),
        ], 'layouts/app');
    }

    /**
     * @return list<array{pregunta: string, respuesta: string}>
     */
    private function questionsFromPost(): array
    {
        $raw = $_POST['questions'] ?? [];
        $questions = [];

        if (!is_array($raw)) {
            return $questions;
        }

        foreach ($raw as $question) {
            if (!is_array($question)) {
                continue;
            }

            $questions[] = [
                'pregunta' => trim((string) ($question['pregunta'] ?? '')),
                'respuesta' => trim((string) ($question['respuesta'] ?? '')),
            ];
        }

        return $questions;
    }
}
