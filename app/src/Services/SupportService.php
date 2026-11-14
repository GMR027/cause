<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Repositories\SupportRepository;
use Cauce\Repositories\UserRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Conversaciones de soporte entre usuarios y administración.
 */
final class SupportService
{
    public const MAX_MESSAGE = 4000;

    private SupportRepository $repo;

    public function __construct(?SupportRepository $repo = null)
    {
        $this->repo = $repo ?? new SupportRepository();
    }

    /**
     * @param array<string, mixed> $user
     */
    public function create(array $user, string $subject, string $message): int
    {
        $subject = $this->subject($subject);
        $message = $this->message($message);
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $id = $this->repo->createConversation((int) $user['id'], $subject);
            $this->repo->addMessage($id, (int) $user['id'], 'usuario', (int) $user['id'], $message);
            $this->repo->markUserRead($id, (int) $user['id']);
            $db->commit();

            return $id;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $user
     */
    public function reply(array $user, int $conversationId, string $message): void
    {
        $conversation = $this->repo->findForUser($conversationId, (int) $user['id']);

        if ($conversation === null) {
            throw new RuntimeException('La conversación no existe.');
        }

        $message = $this->message($message);
        $this->repo->addMessage(
            $conversationId,
            (int) $user['id'],
            'usuario',
            (int) $user['id'],
            $message
        );
        $this->repo->markUserRead($conversationId, (int) $user['id']);
    }

    /**
     * @param array<string, mixed> $admin
     */
    public function adminReply(array $admin, int $conversationId, string $message): void
    {
        $conversation = $this->repo->find($conversationId);

        if ($conversation === null) {
            throw new RuntimeException('La conversación no existe.');
        }

        $message = $this->message($message);
        $messageId = $this->repo->addMessage(
            $conversationId,
            (int) $conversation['user_id'],
            'admin',
            (int) $admin['id'],
            $message
        );
        $this->repo->markAdminRead($conversationId);

        $user = (new UserRepository())->findById((int) $conversation['user_id']);

        if ($user !== null) {
            try {
                (new NotificationService())->notifySupportReply(
                    $user,
                    $conversationId,
                    (string) $conversation['asunto'],
                    $message,
                    $messageId
                );
            } catch (Throwable) {
                // La respuesta queda guardada aunque falle la notificación.
            }
        }
    }

    public function close(int $conversationId): void
    {
        if ($this->repo->find($conversationId) === null) {
            throw new RuntimeException('La conversación no existe.');
        }

        $this->repo->setStatus($conversationId, 'cerrada');
    }

    public function reopen(int $conversationId): void
    {
        if ($this->repo->find($conversationId) === null) {
            throw new RuntimeException('La conversación no existe.');
        }

        $this->repo->setStatus($conversationId, 'abierta');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function userList(array $user): array
    {
        return $this->repo->forUser((int) $user['id']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function adminList(string $filter = 'todas'): array
    {
        return $this->repo->forAdmin($filter);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $id, int $userId): ?array
    {
        return $this->repo->findForUser($id, $userId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messages(int $conversationId): array
    {
        return $this->repo->messages($conversationId);
    }

    public function markUserRead(int $conversationId, int $userId): void
    {
        $this->repo->markUserRead($conversationId, $userId);
    }

    public function markAdminRead(int $conversationId): void
    {
        $this->repo->markAdminRead($conversationId);
    }

    public function unreadForUser(int $userId): int
    {
        return $this->repo->unreadForUser($userId);
    }

    public function unreadForAdmin(): int
    {
        return $this->repo->unreadForAdmin();
    }

    private function subject(string $subject): string
    {
        $subject = trim($subject);

        if (mb_strlen($subject) < 3 || mb_strlen($subject) > 160) {
            throw new InvalidArgumentException('El asunto debe tener entre 3 y 160 caracteres.');
        }

        return $subject;
    }

    private function message(string $message): string
    {
        $message = trim($message);

        if (mb_strlen($message) < 2) {
            throw new InvalidArgumentException('Escribe un mensaje.');
        }

        if (mb_strlen($message) > self::MAX_MESSAGE) {
            throw new InvalidArgumentException('El mensaje es demasiado largo.');
        }

        return $message;
    }
}
