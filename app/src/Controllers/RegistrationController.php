<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\I18n;
use Cauce\Helpers\Logger;
use Cauce\Helpers\Session;
use Cauce\Helpers\Validator;
use Cauce\Helpers\View;
use Cauce\Repositories\UserRepository;
use Cauce\Services\AuthService;
use Cauce\Services\RecoveryService;
use Cauce\Services\RegistrationService;
use PDOException;
use Throwable;

final class RegistrationController
{
    public function showRegister(): void
    {
        if (AuthService::check()) {
            redirect('/');
        }

        echo View::render('auth/register', [
            'title' => __('auth.register.title'),
            'old' => $this->defaults(),
            'errors' => [],
        ], 'layouts/auth');
    }

    public function register(): void
    {
        if (AuthService::check()) {
            redirect('/');
        }

        $old = [
            'alias' => (string) input('alias', ''),
            'email' => strtolower((string) input('email', '')),
            'idioma' => (string) input('idioma', I18n::current()),
            'questions' => $this->questionsFromPost(),
        ];

        $validator = new Validator([
            'alias' => $old['alias'],
            'email' => $old['email'],
            'password' => input('password', '') ?? '',
            'password_confirmation' => input('password_confirmation', '') ?? '',
            'idioma' => $old['idioma'],
        ]);

        $validator
            ->required('alias')
            ->minLength('alias', 3)
            ->maxLength('alias', 30)
            ->regex('alias', '/^[A-Za-z0-9_-]+$/', null, 'auth.errors.alias_format')
            ->required('email')
            ->email('email')
            ->maxLength('email', 190)
            ->password('password')
            ->confirmed('password', 'password_confirmation')
            ->in('idioma', I18n::SUPPORTED);

        $errors = $validator->errors();

        foreach ($old['questions'] as $question) {
            if (trim($question['pregunta']) === '' || trim($question['respuesta']) === '') {
                $errors[] = __('auth.errors.questions_required');
                break;
            }

            if (mb_strlen(trim($question['pregunta'])) > 255 || mb_strlen(trim($question['respuesta'])) > 255) {
                $errors[] = __('common.validation.max', [':field' => __('common.fields.question'), ':max' => '255']);
                break;
            }
        }

        $users = new UserRepository();

        if (!$validator->fails() && count($old['questions']) !== 3) {
            $errors[] = __('auth.errors.questions_required');
        }

        if (!$validator->fails() && $errors === [] && $users->aliasExists($old['alias'])) {
            $errors[] = __('auth.errors.alias_taken');
        }

        if (!$validator->fails() && $errors === [] && $users->emailExists($old['email'])) {
            $errors[] = __('auth.errors.email_taken');
        }

        if ($errors !== []) {
            echo View::render('auth/register', [
                'title' => __('auth.register.title'),
                'old' => $old,
                'errors' => $errors,
            ], 'layouts/auth');

            return;
        }

        try {
            $result = (new RegistrationService())->register([
                'alias' => $old['alias'],
                'email' => $old['email'],
                'password' => (string) input('password', ''),
                'idioma' => $old['idioma'],
                'questions' => $old['questions'],
            ]);
        } catch (PDOException $exception) {
            Logger::warning('Registro duplicado o fallido', ['error' => $exception->getMessage()]);

            $message = str_contains($exception->getMessage(), 'uq_users_alias')
                ? __('auth.errors.alias_taken')
                : (str_contains($exception->getMessage(), 'uq_users_email')
                    ? __('auth.errors.email_taken')
                    : __('auth.errors.generic'));

            echo View::render('auth/register', [
                'title' => __('auth.register.title'),
                'old' => $old,
                'errors' => [$message],
            ], 'layouts/auth');

            return;
        } catch (Throwable $exception) {
            Logger::error('No se pudo registrar el usuario', ['error' => $exception->getMessage()]);

            echo View::render('auth/register', [
                'title' => __('auth.register.title'),
                'old' => $old,
                'errors' => [__('auth.errors.generic')],
            ], 'layouts/auth');

            return;
        }

        AuthService::login($result['user']);
        Session::set('_registration_code', $result['code']);
        Flash::success('auth.flash.registered');

        redirect('/registro/codigo');
    }

    public function showCode(): void
    {
        $code = Session::get('_registration_code');

        if (!is_string($code) || $code === '') {
            redirect('/');
        }

        echo View::render('auth/codigo', [
            'title' => __('auth.code.title'),
            'code' => (new RecoveryService())->formatCode($code),
        ], 'layouts/auth');
    }

    public function continueAfterCode(): void
    {
        Session::forget('_registration_code');
        redirect('/onboarding');
    }

    /**
     * @return array{alias: string, email: string, idioma: string, questions: list<array{pregunta: string, respuesta: string}>}
     */
    private function defaults(): array
    {
        $defaults = lang_array('auth.register.default_questions');
        $questions = [];

        for ($i = 0; $i < 3; $i++) {
            $questions[] = [
                'pregunta' => (string) ($defaults[$i] ?? ''),
                'respuesta' => '',
            ];
        }

        return [
            'alias' => '',
            'email' => '',
            'idioma' => I18n::current(),
            'questions' => $questions,
        ];
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
