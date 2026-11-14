<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

/**
 * Conversaciones y mensajes de soporte.
 */
final class SupportRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function createConversation(int $userId, string $subject): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO soporte_conversaciones
                (user_id, asunto, estado, ultimo_mensaje_at, ultimo_autor,
                 usuario_leido_at, admin_leido_at, created_at, updated_at)
             VALUES (?, ?, "abierta", NULL, NULL, NOW(), NULL, NOW(), NOW())'
        );
        $stmt->execute([$userId, $subject]);

        return (int) $this->db->lastInsertId();
    }

    public function addMessage(
        int $conversationId,
        int $userId,
        string $author,
        ?int $authorId,
        string $message
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO soporte_mensajes
                (conversacion_id, user_id, autor, autor_id, mensaje, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$conversationId, $userId, $author, $authorId, $message]);
        $messageId = (int) $this->db->lastInsertId();

        $update = $this->db->prepare(
            'UPDATE soporte_conversaciones SET
                ultimo_mensaje_at = NOW(),
                ultimo_autor = ?,
                estado = CASE WHEN ? = "usuario" THEN "abierta" ELSE estado END,
                updated_at = NOW()
             WHERE id = ?'
        );
        $update->execute([$author, $author, $conversationId]);

        return $messageId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, u.alias, u.email, u.idioma, u.moneda_activa
             FROM soporte_conversaciones c
             JOIN users u ON u.id = c.user_id
             WHERE c.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM soporte_conversaciones WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messages(int $conversationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, u.alias AS autor_alias
             FROM soporte_mensajes m
             LEFT JOIN users u ON u.id = m.autor_id
             WHERE m.conversacion_id = ?
             ORDER BY m.created_at ASC, m.id ASC'
        );
        $stmt->execute([$conversationId]);

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            '
            SELECT c.*,
                (SELECT COUNT(*) FROM soporte_mensajes m
                 WHERE m.conversacion_id = c.id
                   AND m.autor = "admin"
                   AND (c.usuario_leido_at IS NULL OR m.created_at > c.usuario_leido_at)
                ) AS no_leidos,
                (SELECT m.mensaje FROM soporte_mensajes m
                 WHERE m.conversacion_id = c.id
                 ORDER BY m.id DESC LIMIT 1
                ) AS ultimo_mensaje
            FROM soporte_conversaciones c
            WHERE c.user_id = ?
            ORDER BY COALESCE(c.ultimo_mensaje_at, c.created_at) DESC, c.id DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forAdmin(string $filter = 'todas'): array
    {
        $where = '1 = 1';

        if ($filter === 'abiertas') {
            $where = 'c.estado = "abierta"';
        } elseif ($filter === 'cerradas') {
            $where = 'c.estado = "cerrada"';
        }

        $stmt = $this->db->query(
            "
            SELECT c.*, u.alias, u.email,
                (SELECT COUNT(*) FROM soporte_mensajes m
                 WHERE m.conversacion_id = c.id
                   AND m.autor = \"usuario\"
                   AND (c.admin_leido_at IS NULL OR m.created_at > c.admin_leido_at)
                ) AS no_leidos,
                (SELECT m.mensaje FROM soporte_mensajes m
                 WHERE m.conversacion_id = c.id
                 ORDER BY m.id DESC LIMIT 1
                ) AS ultimo_mensaje
            FROM soporte_conversaciones c
            JOIN users u ON u.id = c.user_id
            WHERE {$where}
            ORDER BY no_leidos DESC, COALESCE(c.ultimo_mensaje_at, c.created_at) DESC, c.id DESC"
        );

        return $stmt->fetchAll();
    }

    public function markUserRead(int $id, int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE soporte_conversaciones SET usuario_leido_at = NOW(), updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
    }

    public function markAdminRead(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE soporte_conversaciones SET admin_leido_at = NOW(), updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare(
            'UPDATE soporte_conversaciones SET estado = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$status, $id]);
    }

    public function unreadForUser(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM soporte_mensajes m
             JOIN soporte_conversaciones c ON c.id = m.conversacion_id
             WHERE c.user_id = ?
               AND m.autor = "admin"
               AND (c.usuario_leido_at IS NULL OR m.created_at > c.usuario_leido_at)'
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    public function unreadForAdmin(): int
    {
        $stmt = $this->db->query(
            'SELECT COUNT(*)
             FROM soporte_mensajes m
             JOIN soporte_conversaciones c ON c.id = m.conversacion_id
             WHERE m.autor = "usuario"
               AND (c.admin_leido_at IS NULL OR m.created_at > c.admin_leido_at)'
        );

        return (int) $stmt->fetchColumn();
    }

    public function countForUser(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM soporte_conversaciones WHERE user_id = ?'
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    public function countOpen(): int
    {
        return (int) $this->db->query(
            'SELECT COUNT(*) FROM soporte_conversaciones WHERE estado = "abierta"'
        )->fetchColumn();
    }
}
