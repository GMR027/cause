<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\Logger;
use Cauce\Helpers\Session;
use Cauce\Helpers\Validator;
use Cauce\Helpers\View;
use Cauce\Repositories\RecoveryRepository;
use Cauce\Repositories\UserRepository;
use Cauce\Services\RecoveryService;
use Throwable;

final class RecoveryController
{
    private RecoveryService $recovery;

    private RecoveryRepository $recoveryRepository;

    private UserRepository $users;

    public function __construct()
    {
        $this->recovery = new RecoveryService();
        $this->recoveryRepository = new RecoveryRepository();
        $this->users = new UserRepository();
    }

    public function index(): void
    {
        echo View::render('auth/recovery/index', [
            'title' => __('auth.recovery.title'),
        ], 'layouts/auth');
    }

    public function showQuestionsEmail(): void
    {
        $this->renderQuestionsEmail('');
    }

    public function submitQuestionsEmail(): void
    {
        $email = strtolower((string) input('email', ''));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->renderQuestionsEmail($email, [__('common.validation.email')]);
            return;
        }

        $user = $this->users->findByEmail($email);

        if ($user === null) {
            $this->renderQuestionsEmail($email, [__('auth.recovery.email_not_found')]);
            return;
        }

        $questions = $this->recoveryRepository->questionsForUser((int) $user['id']);

        if (count($questions) !== 3) {
            $this->renderQuestionsEmail($email, [__('auth.recovery.no_questions')]);
            return;
        }

        $lock = $this->recovery->lockStatus((int) $user['id']);

        if ($lock['locked']) {
            $this->renderQuestionsEmail($email, [
                __('auth.recovery.locked', [':minutes' => (string) $lock['minutes']]),
            ]);
            return;
        }

        Session::set('_recovery', [
            'user_id' => (int) $user['id'],
            'method' => 'questions',
            'verified' => false,
            'verified_at' => null,
        ]);

        redirect('/recuperar/preguntas/responder');
    }

    public function showQuestions(): void
    {
        $state = $this->state('questions');

        if ($state === null) {
            redirect('/recuperar/preguntas');
        }

        $user = $this->users->findById((int) $state['user_id']);

        if ($user === null) {
            $this->clearState();
            redirect('/recuperar');
        }

        $questions = $this->recoveryRepository->questionsForUser((int) $user['id']);

        if (count($questions) !== 3) {
            $this->clearState();
            Flash::error('auth.recovery.no_questions');
            redirect('/recuperar');
        }

        $lock = $this->recovery->lockStatus((int) $user['id']);

        echo View::render('auth/recovery/questions', [
            'title' => __('auth.recovery.questions_heading'),
            'email' => $user['email'],
            'questions' => $questions,
            'locked' => $lock['locked'],
            'minutes' => $lock['minutes'],
        ], 'layouts/auth');
    }

    public function verifyQuestions(): void
    {
        $state = $this->state('questions');

        if ($state === null) {
            redirect('/recuperar/preguntas');
        }

        $userId = (int) $state['user_id'];
        $lock = $this->recovery->lockStatus($userId);

        if ($lock['locked']) {
            Flash::error('auth.recovery.locked', [':minutes' => (string) $lock['minutes']]);
            redirect('/recuperar/preguntas/responder');
        }

        $questions = $this->recoveryRepository->questionsForUser($userId);
        $answers = $_POST['answers'] ?? [];
        $answers = is_array($answers) ? array_values($answers) : [];
        $success = $this->recovery->verifyQuestions($questions, $answers);

        $this->recovery->recordAttempt($userId, $success);

        if ($success) {
            Session::set('_recovery', [
                'user_id' => $userId,
                'method' => 'questions',
                'verified' => true,
                'verified_at' => time(),
            ]);

            Flash::success('auth.flash.verified');
            redirect('/recuperar/restablecer');
        }

        $lock = $this->recovery->lockStatus($userId);

        if ($lock['locked']) {
            Flash::error('auth.recovery.locked', [':minutes' => (string) $lock['minutes']]);
        } else {
            $remaining = max(0, (int) config('auth.recovery.max_attempts', 3) - $lock['attempts']);
            Flash::error('auth.recovery.invalid_answers');
            Flash::warning('auth.recovery.remaining_attempts', [':count' => (string) $remaining]);
        }

        redirect('/recuperar/preguntas/responder');
    }

    public function showCode(): void
    {
        echo View::render('auth/recovery/code', [
            'title' => __('auth.recovery.code_heading'),
            'old' => ['email' => ''],
            'errors' => [],
        ], 'layouts/auth');
    }

    public function verifyCode(): void
    {
        $email = strtolower((string) input('email', ''));
        $code = (string) input('code', '');
        $errors = [];

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || $code === '') {
            $errors[] = __('auth.recovery.invalid_code');
        } else {
            $user = $this->users->findByEmail($email);

            if ($user === null) {
                $errors[] = __('auth.recovery.invalid_code');
            } else {
                $codeId = $this->recovery->verifyCode((int) $user['id'], $code);

                if ($codeId === null) {
                    $errors[] = __('auth.recovery.invalid_code');
                } else {
                    Session::set('_recovery', [
                        'user_id' => (int) $user['id'],
                        'method' => 'code',
                        'verified' => true,
                        'verified_at' => time(),
                        'code_id' => $codeId,
                    ]);

                    Flash::success('auth.flash.code_verified');
                    redirect('/recuperar/restablecer');
                }
            }
        }

        echo View::render('auth/recovery/code', [
            'title' => __('auth.recovery.code_heading'),
            'old' => ['email' => $email],
            'errors' => $errors,
        ], 'layouts/auth');
    }

    public function contact(): void
    {
        echo View::render('auth/recovery/contact', [
            'title' => __('auth.recovery.contact_heading'),
        ], 'layouts/auth');
    }

    public function showReset(): void
    {
        $state = $this->verifiedState();

        if ($state === null) {
            redirect('/recuperar');
        }

        echo View::render('auth/recovery/reset', [
            'title' => __('auth.reset.title'),
            'errors' => [],
        ], 'layouts/auth');
    }

    public function reset(): void
    {
        $state = $this->verifiedState();

        if ($state === null) {
            redirect('/recuperar');
        }

        $validator = new Validator([
            'password' => input('password', '') ?? '',
            'password_confirmation' => input('password_confirmation', '') ?? '',
        ]);

        $validator
            ->password('password')
            ->confirmed('password', 'password_confirmation');

        if ($validator->fails()) {
            echo View::render('auth/recovery/reset', [
                'title' => __('auth.reset.title'),
                'errors' => $validator->errors(),
            ], 'layouts/auth');

            return;
        }

        try {
            $this->recovery->resetPassword(
                (int) $state['user_id'],
                (string) input('password', ''),
                isset($state['code_id']) ? (int) $state['code_id'] : null
            );
        } catch (Throwable $exception) {
            Logger::error('No se pudo restablecer la contraseña', ['error' => $exception->getMessage()]);

            echo View::render('auth/recovery/reset', [
                'title' => __('auth.reset.title'),
                'errors' => [__('auth.errors.generic')],
            ], 'layouts/auth');

            return;
        }

        $this->clearState();
        Flash::success('auth.flash.password_reset');

        redirect('/login');
    }

    /**
     * @param list<string> $errors
     */
    private function renderQuestionsEmail(string $email, array $errors = []): void
    {
        echo View::render('auth/recovery/questions-email', [
            'title' => __('auth.recovery.email_heading'),
            'old' => ['email' => $email],
            'errors' => $errors,
        ], 'layouts/auth');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function state(?string $method = null): ?array
    {
        $state = Session::get('_recovery');

        if (!is_array($state) || empty($state['user_id'])) {
            return null;
        }

        if ($method !== null && ($state['method'] ?? null) !== $method) {
            return null;
        }

        return $state;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function verifiedState(): ?array
    {
        $state = $this->state();

        if ($state === null || empty($state['verified']) || empty($state['verified_at'])) {
            return null;
        }

        $minutes = (int) config('auth.recovery.verified_minutes', 10);

        if (time() - (int) $state['verified_at'] > $minutes * 60) {
            $this->clearState();
            Flash::error('auth.reset.expired');
            return null;
        }

        return $state;
    }

    private function clearState(): void
    {
        Session::forget('_recovery');
    }
}
