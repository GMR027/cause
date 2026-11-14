<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

final class UserRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        return is_array($user) ? $user : null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        return is_array($user) ? $user : null;
    }

    public function aliasExists(string $alias): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE alias = ? LIMIT 1');
        $stmt->execute([$alias]);

        return $stmt->fetchColumn() !== false;
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array{alias: string, email: string, password_hash: string, idioma: string, rol?: string, zona_horaria?: string, onboarding_completado?: int} $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users
                (alias, email, password_hash, moneda_activa, zona_horaria, idioma, rol, onboarding_completado)
             VALUES
                (:alias, :email, :password_hash, :moneda_activa, :zona_horaria, :idioma, :rol, :onboarding_completado)'
        );

        $stmt->execute([
            ':alias' => $data['alias'],
            ':email' => $data['email'],
            ':password_hash' => $data['password_hash'],
            ':moneda_activa' => $data['moneda_activa'] ?? 'MXN',
            ':zona_horaria' => $data['zona_horaria'] ?? (string) config('app.timezone', 'America/Mexico_City'),
            ':idioma' => $data['idioma'],
            ':rol' => $data['rol'] ?? 'user',
            ':onboarding_completado' => $data['onboarding_completado'] ?? 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updatePassword(int $id, string $hash, bool $forceChange = false): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET password_hash = ?, must_change_password = ?, password_changed_at = NOW(), updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$hash, $forceChange ? 1 : 0, $id]);
    }

    public function setMustChangePassword(int $id, bool $required): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET must_change_password = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$required ? 1 : 0, $id]);
    }

    public function setLastLogin(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function setEstado(int $id, string $estado, ?string $motivo = null): void
    {
        $suspendido = $estado === 'suspendido';
        $stmt = $this->db->prepare(
            'UPDATE users
             SET estado = ?, suspendido_motivo = ?, suspendido_at = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$estado, $suspendido ? $motivo : null, $suspendido ? gmdate('Y-m-d H:i:s') : null, $id]);
    }

    public function updateEmail(int $id, string $email): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET email = ?, email_verificado_at = NULL, email_verificacion_token = NULL,
                 email_verificacion_expira = NULL, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$email, $id]);
    }

    public function setPin(int $id, string $hash): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET pin_hash = ?, pin_attempts = 0, pin_locked_until = NULL, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$hash, $id]);
    }

    public function clearPin(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET pin_hash = NULL, pin_attempts = 0, pin_locked_until = NULL, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$id]);
    }

    public function registerPinFailure(int $id, int $attempts, ?string $lockedUntil): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET pin_attempts = ?, pin_locked_until = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$attempts, $lockedUntil, $id]);
    }

    public function resetPinAttempts(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET pin_attempts = 0, pin_locked_until = NULL, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$id]);
    }

    public function updateDebtStrategy(int $id, string $strategy): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET estrategia_deuda = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$strategy, $id]);
    }

    public function updateCurrency(int $id, string $code): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET moneda_activa = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$code, $id]);
    }

    public function updateCash(int $id, ?int $cents): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET efectivo_manual_centavos = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$cents, $id]);
    }

    public function setAnalyticsOptOut(int $id, bool $optOut): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET analytics_opt_out = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$optOut ? 1 : 0, $id]);
    }

    public function activeAdminCount(): int
    {
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM users WHERE rol = 'admin' AND estado = 'activo'"
        )->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $where = '';
        $params = [];

        if (trim($query) !== '') {
            $where = 'WHERE u.alias LIKE ? OR u.email LIKE ?';
            $like = '%' . trim($query) . '%';
            $params = [$like, $like];
        }

        $statement = $this->db->prepare(
            "SELECT u.id, u.alias, u.email, u.rol, u.estado, u.idioma, u.moneda_activa,
                    u.onboarding_completado, u.created_at, u.last_login_at,
                    u.suspendido_motivo, u.suspendido_at, u.hogar_id,
                    (SELECT COUNT(*) FROM estados_cuenta e WHERE e.user_id = u.id) AS pdf_count,
                    (SELECT COALESCE(SUM(e.tamano_bytes), 0) FROM estados_cuenta e WHERE e.user_id = u.id) AS pdf_bytes,
                    (SELECT COUNT(*) FROM sesiones s WHERE s.user_id = u.id) AS session_count
             FROM users u
             {$where}
             ORDER BY u.created_at DESC, u.id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function countSearch(string $query): int
    {
        if (trim($query) === '') {
            return $this->count();
        }

        $like = '%' . trim($query) . '%';
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM users WHERE alias LIKE ? OR email LIKE ?'
        );
        $statement->execute([$like, $like]);

        return (int) $statement->fetchColumn();
    }

    public function updateIdioma(int $id, string $idioma): void
    {
        $stmt = $this->db->prepare('UPDATE users SET idioma = ? WHERE id = ?');
        $stmt->execute([$idioma, $id]);
    }

    public function updateTone(int $id, string $tone): void
    {
        $stmt = $this->db->prepare('UPDATE users SET tono_notificaciones = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$tone, $id]);
    }

    public function setPushPausedUntil(int $id, ?string $until): void
    {
        $stmt = $this->db->prepare('UPDATE users SET push_pausado_hasta = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$until, $id]);
    }

    /**
     * @param array<string, mixed> $state
     */
    public function updateOnboarding(int $id, array $state, bool $completed): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET onboarding_estado = ?, onboarding_completado = ?, updated_at = NOW()
             WHERE id = ?'
        );

        $stmt->execute([
            json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $completed ? 1 : 0,
            $id,
        ]);
    }

    public function updateFromOnboarding(int $id, string $moneda, string $tipoIngreso, ?string $recurrencia): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET moneda_activa = ?, tipo_ingreso = ?, recurrencia = ?, updated_at = NOW()
             WHERE id = ?'
        );

        $stmt->execute([$moneda, $tipoIngreso, $recurrencia, $id]);
    }

    public function updateRecurrence(
        int $id,
        string $recurrencia,
        ?string $anterior,
        ?string $cambioDesde
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET recurrencia = ?, recurrencia_anterior = ?, recurrencia_cambio_desde = ?, updated_at = NOW()
             WHERE id = ?'
        );

        $stmt->execute([$recurrencia, $anterior, $cambioDesde, $id]);
    }

    public function updatePeriodoInicioDia(int $id, int $day): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET periodo_inicio_dia = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([max(1, min(31, $day)), $id]);
    }

    /**
     * @param array<string, int>|null $reparto
     */
    public function updateExtraReparto(int $id, ?array $reparto): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET extra_reparto_json = ?, updated_at = NOW() WHERE id = ?'
        );

        $stmt->execute([
            $reparto === null ? null : json_encode($reparto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $id,
        ]);
    }

    /**
     * @param array{modo: string, color: string, enfoque: string, fondo: string, textura: string} $theme
     */
    public function updateTheme(int $id, array $theme): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET tema_modo = ?, tema_color_principal = ?, tema_enfoque = ?,
                 tema_efecto_fondo = ?, tema_textura = ?, updated_at = NOW()
             WHERE id = ?'
        );

        $stmt->execute([
            $theme['modo'],
            $theme['color'],
            $theme['enfoque'],
            $theme['fondo'],
            $theme['textura'],
            $id,
        ]);
    }

    /**
     * @param list<array{pregunta: string, respuesta_hash: string}> $questions
     */
    public function replaceQuestions(int $userId, array $questions): void
    {
        $delete = $this->db->prepare('DELETE FROM preguntas_recuperacion WHERE user_id = ?');
        $delete->execute([$userId]);

        $insert = $this->db->prepare(
            'INSERT INTO preguntas_recuperacion (user_id, pregunta, respuesta_hash, orden)
             VALUES (?, ?, ?, ?)'
        );

        foreach (array_values($questions) as $index => $question) {
            $insert->execute([
                $userId,
                $question['pregunta'],
                $question['respuesta_hash'],
                $index + 1,
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $stmt = $this->db->query(
            'SELECT id, alias, email, rol, idioma, onboarding_completado, created_at
             FROM users
             ORDER BY created_at DESC, id DESC'
        );

        return $stmt->fetchAll();
    }

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }
}
