<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\View;
use Cauce\Repositories\MetodoRepository;
use Cauce\Repositories\NotificationRepository;
use Cauce\Services\AuthService;
use Cauce\Services\MethodRecommender;
use Cauce\Services\OnboardingService;
use Cauce\Services\PushService;
use Throwable;

/**
 * Onboarding lineal y retomable.
 */
final class OnboardingController
{
    private OnboardingService $onboarding;

    private MetodoRepository $metodos;

    private MethodRecommender $recommender;

    public function __construct()
    {
        $this->onboarding = new OnboardingService();
        $this->metodos = new MetodoRepository();
        $this->recommender = new MethodRecommender();
    }

    public function index(): void
    {
        $user = $this->user();

        if ($this->onboarding->isComplete($user)) {
            redirect('/inicio');
        }

        $state = $this->onboarding->state($user);

        redirect($this->onboarding->stepRoute((string) $state['paso']));
    }

    public function showTutorial(): void
    {
        $user = $this->user();
        $state = $this->onboarding->state($user);

        $this->render('onboarding/tutorial', 'tutorial', $state, [
            'title' => __('onboarding.tutorial.title'),
        ]);
    }

    public function saveTutorial(): void
    {
        $user = $this->user();
        $state = $this->onboarding->save($user, [
            'tutorial_visto' => true,
            'paso' => 'simulador',
        ]);

        redirect($this->onboarding->stepRoute('simulador'));
    }

    public function showSimulador(): void
    {
        $user = $this->user();
        $state = $this->onboarding->state($user);

        $this->renderSimulator($user, $state, []);
    }

    public function saveSimulador(): void
    {
        $user = $this->user();
        $state = $this->onboarding->state($user);
        $errors = [];

        $amount = $this->parseAmount((string) input('monto', ''));
        $isQuizAction = input('accion') === 'quiz';
        $quiz = $this->quizFromPost();
        $quizForState = null;

        if ($amount === null) {
            $errors[] = __('onboarding.errors.amount');
        }

        if ($quiz !== null) {
            $quizErrors = $this->validateQuiz($quiz);

            if ($quizErrors === []) {
                $quizForState = $quiz;
            } elseif ($isQuizAction) {
                $errors = array_merge($errors, $quizErrors);
            }
        }

        if ($isQuizAction && $quiz === null) {
            $errors[] = __('onboarding.errors.quiz');
        }

        if ($errors !== []) {
            $this->renderSimulator($user, $state, $errors);
            return;
        }

        $patch = [
            'monto_centavos' => $amount,
            'metodo_preview' => (string) input('metodo_preview', (string) $state['metodo_preview']),
        ];

        if ($quizForState !== null) {
            $patch['quiz'] = $quizForState;
        }

        $newState = $this->onboarding->save($user, $patch);

        if ($isQuizAction) {
            $this->renderSimulator($user, $newState, [], $this->recommender->recommend($newState['quiz']));
            return;
        }

        $this->onboarding->save($user, ['paso' => 'metodo']);

        redirect($this->onboarding->stepRoute('metodo'));
    }

    public function showMetodo(): void
    {
        $user = $this->user();
        $state = $this->onboarding->state($user);
        $methods = $this->metodos->byCodes((array) config('onboarding.selectable_methods', []));
        $recommended = $this->hasQuiz($state['quiz']) ? $this->recommender->recommend($state['quiz']) : null;

        $this->render('onboarding/metodo', 'metodo', $state, [
            'title' => __('onboarding.metodo.title'),
            'methods' => $methods,
            'recommended' => $recommended,
            'recommendedMethod' => $recommended !== null ? $this->findMethod($methods, $recommended) : null,
        ]);
    }

    public function saveMetodo(): void
    {
        $user = $this->user();
        $state = $this->onboarding->state($user);
        $metodo = (string) input('metodo', '');

        if (!in_array($metodo, (array) config('onboarding.selectable_methods', []), true)) {
            $methods = $this->metodos->byCodes((array) config('onboarding.selectable_methods', []));
            $this->render('onboarding/metodo', 'metodo', $state, [
                'title' => __('onboarding.metodo.title'),
                'methods' => $methods,
                'recommended' => null,
                'recommendedMethod' => null,
                'errors' => [__('onboarding.errors.method')],
            ]);
            return;
        }

        $state = $this->onboarding->save($user, [
            'metodo' => $metodo,
            'paso' => 'moneda',
        ]);

        redirect($this->onboarding->stepRoute('moneda'));
    }

    public function showMoneda(): void
    {
        $user = $this->user();
        $state = $this->onboarding->state($user);

        $this->render('onboarding/moneda', 'moneda', $state, [
            'title' => __('onboarding.moneda.title'),
            'currencies' => (array) config('onboarding.currencies', []),
        ]);
    }

    public function saveMoneda(): void
    {
        $user = $this->user();
        $currency = strtoupper((string) input('moneda', ''));

        if (!in_array($currency, (array) config('onboarding.currencies', []), true)) {
            $this->render('onboarding/moneda', 'moneda', $this->onboarding->state($user), [
                'title' => __('onboarding.moneda.title'),
                'currencies' => (array) config('onboarding.currencies', []),
                'errors' => [__('onboarding.errors.currency')],
            ]);
            return;
        }

        $this->onboarding->save($user, ['moneda' => $currency, 'paso' => 'tipo-ingreso']);
        redirect($this->onboarding->stepRoute('tipo-ingreso'));
    }

    public function showTipoIngreso(): void
    {
        $user = $this->user();
        $state = $this->onboarding->state($user);

        $this->render('onboarding/tipo-ingreso', 'tipo-ingreso', $state, [
            'title' => __('onboarding.tipo_ingreso.title'),
            'types' => (array) config('onboarding.income_types', []),
        ]);
    }

    public function saveTipoIngreso(): void
    {
        $user = $this->user();
        $type = (string) input('tipo_ingreso', '');

        if (!in_array($type, (array) config('onboarding.income_types', []), true)) {
            $this->render('onboarding/tipo-ingreso', 'tipo-ingreso', $this->onboarding->state($user), [
                'title' => __('onboarding.tipo_ingreso.title'),
                'types' => (array) config('onboarding.income_types', []),
                'errors' => [__('onboarding.errors.income_type')],
            ]);
            return;
        }

        $this->onboarding->save($user, ['tipo_ingreso' => $type, 'paso' => 'configuracion']);
        redirect($this->onboarding->stepRoute('configuracion'));
    }

    public function showConfiguracion(): void
    {
        $user = $this->user();
        $state = $this->onboarding->state($user);

        $this->render('onboarding/configuracion', 'configuracion', $state, [
            'title' => __('onboarding.configuracion.title'),
        ]);
    }

    public function saveConfiguracion(): void
    {
        $user = $this->user();
        $mode = (string) input('configuracion', '');

        if (!in_array($mode, (array) config('onboarding.configurations', []), true)) {
            $this->render('onboarding/configuracion', 'configuracion', $this->onboarding->state($user), [
                'title' => __('onboarding.configuracion.title'),
                'errors' => [__('onboarding.errors.configuration')],
            ]);
            return;
        }

        $patch = [
            'configuracion' => $mode,
            'paso' => $mode === 'rapida' ? 'primer-ingreso' : 'recurrencia',
        ];

        if ($mode === 'rapida') {
            $patch['recurrencia'] = null;
        }

        $this->onboarding->save($user, $patch);
        redirect($this->onboarding->stepRoute((string) $patch['paso']));
    }

    public function showRecurrencia(): void
    {
        $user = $this->user();
        $state = $this->onboarding->state($user);

        $this->render('onboarding/recurrencia', 'recurrencia', $state, [
            'title' => __('onboarding.recurrencia.title'),
            'recurrences' => (array) config('onboarding.recurrences', []),
        ]);
    }

    public function saveRecurrencia(): void
    {
        $user = $this->user();
        $recurrence = (string) input('recurrencia', '');

        if (!in_array($recurrence, (array) config('onboarding.recurrences', []), true)) {
            $this->render('onboarding/recurrencia', 'recurrencia', $this->onboarding->state($user), [
                'title' => __('onboarding.recurrencia.title'),
                'recurrences' => (array) config('onboarding.recurrences', []),
                'errors' => [__('onboarding.errors.recurrence')],
            ]);
            return;
        }

        $this->onboarding->save($user, ['recurrencia' => $recurrence, 'paso' => 'primer-ingreso']);
        redirect($this->onboarding->stepRoute('primer-ingreso'));
    }

    public function showPrimerIngreso(): void
    {
        $user = $this->user();
        $state = $this->onboarding->state($user);

        $this->render('onboarding/primer-ingreso', 'primer-ingreso', $state, [
            'title' => __('onboarding.primer_ingreso.title'),
        ]);
    }

    public function savePrimerIngreso(): void
    {
        $user = $this->user();
        $answer = (string) input('respuesta', '');

        if (!in_array($answer, ['si', 'no'], true)) {
            $this->render('onboarding/primer-ingreso', 'primer-ingreso', $this->onboarding->state($user), [
                'title' => __('onboarding.primer_ingreso.title'),
                'errors' => [__('onboarding.errors.generic')],
            ]);
            return;
        }

        $state = $this->onboarding->save($user, [
            'primer_ingreso' => $answer,
            'paso' => 'notificaciones',
        ]);

        if ($answer === 'si') {
            try {
                // El método debe estar activo antes de abrir /ingresos/nuevo;
                // el onboarding se completa después de elegir notificaciones.
                $this->onboarding->provision($user, $state);
            } catch (Throwable $exception) {
                \Cauce\Helpers\Logger::error('No se pudo preparar el primer ingreso', [
                    'user_id' => $user['id'],
                    'error' => $exception->getMessage(),
                ]);

                $this->render('onboarding/primer-ingreso', 'primer-ingreso', $state, [
                    'title' => __('onboarding.primer_ingreso.title'),
                    'errors' => [__('onboarding.errors.generic')],
                ]);

                return;
            }

            redirect('/ingresos/nuevo?onboarding=1');
        }

        redirect($this->onboarding->stepRoute('notificaciones'));
    }

    public function showNotificaciones(): void
    {
        $user = $this->user();
        $state = $this->onboarding->state($user);
        $push = new PushService();

        $this->render('onboarding/notificaciones', 'notificaciones', $state, [
            'title' => __('onboarding.notificaciones.title'),
            'push_enabled' => $push->enabled(),
            'push_public_key' => $push->publicKey(),
        ]);
    }

    public function finish(): void
    {
        $user = $this->user();
        $answer = (string) input('respuesta', '');

        if (!in_array($answer, ['activadas', 'ahora_no'], true)) {
            $this->render('onboarding/notificaciones', 'notificaciones', $this->onboarding->state($user), [
                'title' => __('onboarding.notificaciones.title'),
                'errors' => [__('onboarding.errors.generic')],
            ]);
            return;
        }

        $state = $this->onboarding->save($user, ['notificaciones' => $answer]);
        (new NotificationRepository())->setAllActive((int) $user['id'], $answer === 'activadas');
        $_SESSION['_notifications_check'] = 0;

        try {
            $this->onboarding->complete($user, $state);
        } catch (Throwable $exception) {
            \Cauce\Helpers\Logger::error('No se pudo completar el onboarding', [
                'user_id' => $user['id'],
                'error' => $exception->getMessage(),
            ]);

            $this->render('onboarding/notificaciones', 'notificaciones', $state, [
                'title' => __('onboarding.notificaciones.title'),
                'errors' => [__('onboarding.errors.generic')],
            ]);
            return;
        }

        Flash::success('onboarding.flash.completed');
        redirect('/inicio');
    }

    /**
     * @param list<string> $errors
     */
    private function renderSimulator(array $user, array $state, array $errors, ?string $recommended = null): void
    {
        $methods = $this->metodos->byCodes((array) config('onboarding.simulator_methods', []));
        $recommended ??= $this->hasQuiz($state['quiz']) ? $this->recommender->recommend($state['quiz']) : null;
        $recommendedMethod = $recommended !== null ? $this->metodos->findByCodigo($recommended) : null;

        $this->render('onboarding/simulador', 'simulador', $state, [
            'title' => __('onboarding.simulador.title'),
            'methods' => $methods,
            'recommended' => $recommended,
            'recommendedMethod' => $recommendedMethod,
            'errors' => $errors,
        ]);
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $data
     */
    private function render(string $view, string $step, array $state, array $data): void
    {
        $steps = $this->onboarding->steps();

        echo View::render($view, array_merge([
            'state' => $state,
            'stepKey' => $step,
            'stepIndex' => $this->onboarding->stepIndex($step) + 1,
            'stepTotal' => count($steps),
            'errors' => [],
        ], $data), 'layouts/onboarding');
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
     * @return array{p1: string, p2: string, p3: string}|null
     */
    private function quizFromPost(): ?array
    {
        $raw = $_POST['quiz'] ?? null;

        if (!is_array($raw)) {
            return null;
        }

        $quiz = [
            'p1' => (string) ($raw['p1'] ?? ''),
            'p2' => (string) ($raw['p2'] ?? ''),
            'p3' => (string) ($raw['p3'] ?? ''),
        ];

        if ($quiz['p1'] === '' && $quiz['p2'] === '' && $quiz['p3'] === '') {
            return null;
        }

        return $quiz;
    }

    /**
     * @param array{p1: string, p2: string, p3: string} $quiz
     * @return list<string>
     */
    private function validateQuiz(array $quiz): array
    {
        $allowed = [
            'p1' => ['fijo', 'variable', 'mixto'],
            'p2' => ['si', 'no', 'no_seguro'],
            'p3' => ['ahorrar', 'invertir', 'libertad', 'donar'],
        ];

        $errors = [];

        foreach ($allowed as $key => $options) {
            if (!in_array($quiz[$key], $options, true)) {
                $errors[] = __('onboarding.errors.quiz');
                break;
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $quiz
     */
    private function hasQuiz(array $quiz): bool
    {
        return ($quiz['p1'] ?? '') !== ''
            && ($quiz['p2'] ?? '') !== ''
            && ($quiz['p3'] ?? '') !== '';
    }

    /**
     * @param list<array<string, mixed>> $methods
     * @return array<string, mixed>|null
     */
    private function findMethod(array $methods, string $code): ?array
    {
        foreach ($methods as $method) {
            if (($method['codigo'] ?? '') === $code) {
                return $method;
            }
        }

        return null;
    }

    private function parseAmount(string $raw): ?int
    {
        $normalized = str_replace([' ', '$', ','], ['', '', '.'], trim($raw));

        if ($normalized === '' || !is_numeric($normalized)) {
            return null;
        }

        $cents = (int) round(((float) $normalized) * 100);
        $max = (int) config('onboarding.max_income_cents', 1000000000000);

        return max(0, min($max, $cents));
    }
}
