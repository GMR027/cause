<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

final class NotificationRepository
{
    private const DEFAULTS = [
        'ingreso_dia_menos_1' => ['09:00:00', 'una_vez'],
        'ingreso_dia_0' => ['09:00:00', 'una_vez'],
        'ingreso_rescate' => ['09:00:00', 'una_vez'],
        'gasto_recurrente' => [null, 'diaria'],
        'recordatorio_deuda' => [null, 'mensual'],
        'alerta_categoria' => [null, 'diaria'],
        'resumen_mensual' => ['10:00:00', 'mensual'],
        'soporte_respuesta' => [null, 'una_vez'],
    ];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function ensureDefaults(int $userId): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO notificaciones_config
                (user_id, tipo, activo, hora, frecuencia, created_at, updated_at)
             VALUES (?, ?, 1, ?, ?, NOW(), NOW())'
        );

        foreach (self::DEFAULTS as $type => [$hour, $frequency]) {
            $stmt->execute([$userId, $type, $hour, $frequency]);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function configForUser(int $userId): array
    {
        $this->ensureDefaults($userId);
        $stmt = $this->db->prepare('SELECT * FROM notificaciones_config WHERE user_id = ?');
        $stmt->execute([$userId]);

        $config = [];

        foreach ($stmt->fetchAll() as $row) {
            $config[(string) $row['tipo']] = $row;
        }

        return $config;
    }

    public function setAllActive(int $userId, bool $active): void
    {
        $this->ensureDefaults($userId);
        $stmt = $this->db->prepare(
            'UPDATE notificaciones_config SET activo = ?, updated_at = NOW() WHERE user_id = ?'
        );
        $stmt->execute([$active ? 1 : 0, $userId]);
    }

    public function updateConfig(
        int $userId,
        string $type,
        bool $active,
        ?string $hour,
        string $frequency
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO notificaciones_config
                (user_id, tipo, activo, hora, frecuencia, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                activo = VALUES(activo), hora = VALUES(hora), frecuencia = VALUES(frecuencia), updated_at = NOW()'
        );
        $stmt->execute([$userId, $type, $active ? 1 : 0, $hour, $frequency]);
    }

    public function create(
        int $userId,
        string $type,
        string $key,
        string $title,
        string $body,
        ?string $periodStart,
        ?string $periodEnd,
        int $push,
        ?int $referenceId = null
    ): ?int {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO notificaciones
                (user_id, tipo, clave, titulo, cuerpo, periodo_inicio, periodo_fin,
                 canal, push_enviado_at, leida, referencia_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW(), NOW())'
        );

        $stmt->execute([
            $userId,
            $type,
            $key,
            $title,
            $body,
            $periodStart,
            $periodEnd,
            'inapp',
            $push ? null : gmdate('Y-m-d H:i:s'),
            $referenceId,
        ]);

        return $stmt->rowCount() > 0 ? (int) $this->db->lastInsertId() : null;
    }

    public function markPushHandled(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE notificaciones SET push_enviado_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $userId, int $limit = 100, bool $onlyUnread = false): array
    {
        $limit = max(1, min(200, $limit));
        $where = 'user_id = ?';

        if ($onlyUnread) {
            $where .= ' AND leida = 0';
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM notificaciones WHERE {$where}
             ORDER BY leida ASC, created_at DESC, id DESC LIMIT {$limit}"
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function unreadCount(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM notificaciones WHERE user_id = ? AND leida = 0');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    public function markRead(int $id, int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE notificaciones SET leida = 1, updated_at = NOW() WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
    }

    public function markAllRead(int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE notificaciones SET leida = 1, updated_at = NOW() WHERE user_id = ? AND leida = 0'
        );
        $stmt->execute([$userId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByKey(int $userId, string $key): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM notificaciones WHERE user_id = ? AND clave = ? LIMIT 1'
        );
        $stmt->execute([$userId, $key]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function subscriptionsForUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM push_suscripciones WHERE user_id = ?');
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function saveSubscription(
        int $userId,
        string $endpoint,
        string $p256dh,
        string $auth,
        ?string $userAgent
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO push_suscripciones
                (user_id, endpoint, endpoint_hash, p256dh, auth, user_agent, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth),
                user_agent = VALUES(user_agent), updated_at = NOW()'
        );
        $stmt->execute([
            $userId,
            $endpoint,
            hash('sha256', $endpoint),
            $p256dh,
            $auth,
            $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
        ]);
    }

    public function deleteSubscription(int $userId, string $endpoint): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM push_suscripciones WHERE user_id = ? AND endpoint_hash = ?'
        );
        $stmt->execute([$userId, hash('sha256', $endpoint)]);
    }
}
