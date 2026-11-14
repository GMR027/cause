<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Helpers\PasswordHasher;
use Cauce\Repositories\RecoveryRepository;
use Cauce\Repositories\SubcategoriaRepository;
use Cauce\Repositories\UserRepository;
use Throwable;

/**
 * Registro transaccional de usuarios.
 */
final class RegistrationService
{
    /**
     * @param array{alias: string, email: string, password: string, idioma: string, questions: list<array{pregunta: string, respuesta: string}>} $data
     * @return array{user: array<string, mixed>, code: string}
     */
    public function register(array $data): array
    {
        $db = Database::connection();
        $users = new UserRepository($db);
        $recovery = new RecoveryService();
        $plainCode = $recovery->generateCode();

        $questions = [];

        foreach ($data['questions'] as $question) {
            $questions[] = [
                'pregunta' => trim($question['pregunta']),
                'respuesta_hash' => $recovery->hashAnswer(trim($question['respuesta'])),
            ];
        }

        try {
            $db->beginTransaction();

            $userId = $users->create([
                'alias' => $data['alias'],
                'email' => $data['email'],
                'password_hash' => PasswordHasher::hash($data['password']),
                'idioma' => $data['idioma'],
                'rol' => 'user',
                'onboarding_completado' => 0,
            ]);

            $users->replaceQuestions($userId, $questions);

            (new RecoveryRepository($db))->createCode($userId, $recovery->hashCode($plainCode));
            (new SubcategoriaRepository($db))->seedForUser($userId);

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        $user = $users->findById($userId);

        if ($user === null) {
            throw new \RuntimeException('No se pudo recuperar el usuario recién creado.');
        }

        return ['user' => $user, 'code' => $plainCode];
    }
}
