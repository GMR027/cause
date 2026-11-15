<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Helpers\Logger;
use Cauce\Repositories\GastoRepository;
use Cauce\Repositories\IngresoRepository;
use Cauce\Repositories\NotificationRepository;
use Cauce\Repositories\SavingsRepository;
use Cauce\Repositories\SummaryRepository;
use Cauce\Repositories\UserRepository;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Motor de notificaciones in-app y Web Push, sin cron.
 */
final class NotificationService
{
    private const CRITICAL = [
        'ingreso_dia_0',
        'ingreso_rescate',
        'gasto_recurrente',
        'alerta_categoria',
        'resumen_mensual',
    ];

    private NotificationRepository $repo;

    public function __construct(?NotificationRepository $repo = null)
    {
        $this->repo = $repo ?? new NotificationRepository();
    }

    /**
     * @param array<string, mixed> $user
     */
    public function process(array $user): void
    {
        $userId = (int) $user['id'];
        $this->repo->ensureDefaults($userId);
        $config = $this->repo->configForUser($userId);
        $pushAllowed = $this->handleVacation($user);

        try {
            $this->incomeReminders($user, $config, $pushAllowed);
        } catch (Throwable $exception) {
            Logger::warning('No se pudieron procesar recordatorios de ingreso', ['error' => $exception->getMessage()]);
        }

        try {
            $this->categoryAlerts($user, $config, $pushAllowed);
        } catch (Throwable $exception) {
            Logger::warning('No se pudieron procesar alertas de categoría', ['error' => $exception->getMessage()]);
        }

        try {
            $this->monthlySummary($user, $config, $pushAllowed);
        } catch (Throwable $exception) {
            Logger::warning('No se pudo generar el resumen mensual', ['error' => $exception->getMessage()]);
        }

        try {
            $this->hitos($user);
        } catch (Throwable $exception) {
            Logger::warning('No se pudieron procesar hitos', ['error' => $exception->getMessage()]);
        }

        try {
            $this->subscriptionReminders($user, $config, $pushAllowed);
        } catch (Throwable $exception) {
            Logger::warning('No se pudieron procesar recordatorios de suscripción', ['error' => $exception->getMessage()]);
        }
    }

    /**
     * Avisos de suscripciones: N días antes del cobro (si la persona lo pidió)
     * y 3 días antes de que termine una prueba gratis. La clave única evita
     * repetir el aviso para el mismo cobro.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $config
     */
    private function subscriptionReminders(array $user, array $config, bool $pushAllowed): void
    {
        if (empty($config['gasto_recurrente']['activo'] ?? 1)) {
            return;
        }

        $timezone = (new PeriodoService())->userTimezone($user);
        $today = new \DateTimeImmutable('today', $timezone);

        foreach ((new \Cauce\Repositories\RecurringExpenseRepository())->allForUser((int) $user['id'], true) as $row) {
            if ((int) ($row['es_suscripcion'] ?? 0) !== 1) {
                continue;
            }

            $next = (new \DateTimeImmutable((string) $row['proxima_ejecucion'], new \DateTimeZone('UTC')))
                ->setTimezone($timezone)->setTime(0, 0);
            $days = (int) $today->diff($next)->format('%r%a');
            $amount = format_money((int) $row['monto_centavos'], (string) $row['moneda']);

            if ($row['recordatorio_dias'] !== null && $days >= 0 && $days <= (int) $row['recordatorio_dias']) {
                $this->emit($user, 'recordatorio_suscripcion', 'sub-reminder:' . (int) $row['id'] . ':' . $next->format('Y-m-d'), [
                    ':name' => (string) $row['nombre'],
                    ':amount' => $amount,
                    ':date' => format_date($next->format('Y-m-d') . ' 12:00:00'),
                ], null, $pushAllowed, (int) $row['id'], '/reportes/suscripciones');
            }

            if (!empty($row['prueba_hasta'])) {
                $trial = new \DateTimeImmutable((string) $row['prueba_hasta'], $timezone);
                $trialDays = (int) $today->diff($trial)->format('%r%a');

                if ($trialDays >= 0 && $trialDays <= 3) {
                    $this->emit($user, 'fin_prueba_suscripcion', 'sub-trial:' . (int) $row['id'] . ':' . $trial->format('Y-m-d'), [
                        ':name' => (string) $row['nombre'],
                        ':amount' => $amount,
                        ':date' => format_date($trial->format('Y-m-d') . ' 12:00:00'),
                    ], null, $pushAllowed, (int) $row['id'], '/reportes/suscripciones');
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $user
     */
    public function notifyRecurring(
        array $user,
        int $gastoId,
        string $name,
        int $amount,
        string $part,
        string $date,
        ?string $currency = null
    ): void {
        $config = $this->repo->configForUser((int) $user['id']);

        if (empty($config['gasto_recurrente']['activo'])) {
            return;
        }

        $this->emit($user, 'gasto_recurrente', 'recurring:' . $gastoId, [
            ':name' => $name,
            ':amount' => format_money($amount, $currency ?? (string) ($user['moneda_activa'] ?? 'MXN')),
            ':part' => $part,
        ], null, $this->pushAllowed($user), $gastoId, '/gastos/ver?id=' . $gastoId);
    }

    /**
     * Recordatorio de pago mínimo de una deuda.
     *
     * No crea un gasto ni modifica el saldo: solo avisa.
     *
     * @param array<string, mixed> $user
     */
    public function notifyDebtReminder(
        array $user,
        int $debtId,
        string $name,
        int $amount,
        string $date,
        ?string $currency = null
    ): void {
        $config = $this->repo->configForUser((int) $user['id']);
        $active = $config['recordatorio_deuda']['activo'] ?? $config['gasto_recurrente']['activo'] ?? 1;

        if (empty($active)) {
            return;
        }

        $this->emit($user, 'recordatorio_deuda', 'debt-reminder:' . $debtId . ':' . $date, [
            ':name' => $name,
            ':amount' => format_money($amount, $currency ?? (string) ($user['moneda_activa'] ?? 'MXN')),
            ':date' => format_date($date . ' 12:00:00'),
        ], null, $this->pushAllowed($user), $debtId, '/deudas');
    }


    /**
     * Aviso de respuesta del equipo de soporte.
     *
     * @param array<string, mixed> $user
     */
    public function notifySupportReply(
        array $user,
        int $conversationId,
        string $subject,
        string $message,
        int $messageId
    ): void {
        $config = $this->repo->configForUser((int) $user['id']);

        if (empty($config['soporte_respuesta']['activo'])) {
            return;
        }

        $preview = trim((string) preg_replace('/\s+/u', ' ', $message));

        $this->emit($user, 'soporte_respuesta', 'support:' . $conversationId . ':' . $messageId, [
            ':subject' => $subject,
            ':preview' => mb_substr($preview, 0, 120),
        ], null, false, $conversationId, '/soporte/ver?id=' . $conversationId);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function handleVacation(array $user): bool
    {
        $until = $user['push_pausado_hasta'] ?? null;

        if ($until === null || trim((string) $until) === '') {
            return true;
        }

        $utc = new DateTimeZone('UTC');
        $date = new DateTimeImmutable((string) $until, $utc);
        $now = new DateTimeImmutable('now', $utc);

        if ($now < $date) {
            return false;
        }

        $key = 'vacation:' . $date->format('YmdHis');
        $this->emit($user, 'vacaciones_reactivacion', $key, [], null, false, null, '/ajustes/notificaciones');
        (new UserRepository())->setPushPausedUntil((int) $user['id'], null);

        return true;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function pushAllowed(array $user): bool
    {
        $until = $user['push_pausado_hasta'] ?? null;

        if ($until === null || trim((string) $until) === '') {
            return true;
        }

        return new DateTimeImmutable('now', new DateTimeZone('UTC'))
            >= new DateTimeImmutable((string) $until, new DateTimeZone('UTC'));
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, array<string, mixed>> $config
     */
    private function incomeReminders(array $user, array $config, bool $pushAllowed): void
    {
        $support = new FinanceSupportService();
        $current = $support->period($user);
        $nextStart = $current['fin_dt']->modify('+1 second');
        $next = (new PeriodoService())->calcularPeriodo($user, $nextStart);
        $nextPeriod = [
            'inicio' => $next['inicio']->format('Y-m-d H:i:s'),
            'fin' => $next['fin']->format('Y-m-d H:i:s'),
            'inicio_dt' => $next['inicio'],
            'fin_dt' => $next['fin'],
        ];
        $timezone = $support->ago($user);
        $now = new DateTimeImmutable('now', $timezone);
        $incomes = new IngresoRepository();
        $hour = $this->hour((string) ($config['ingreso_dia_0']['hora'] ?? '09:00:00'));
        $daily = (string) ($user['recurrencia'] ?? '') === 'diaria';

        foreach ([$current, $nextPeriod] as $period) {
            if ($incomes->existsForPeriod((int) $user['id'], $period['inicio'], $period['fin'])) {
                continue;
            }

            $start = $period['inicio_dt']->setTimezone($timezone);
            $dayBefore = $start->modify('-1 day')->setTime($hour[0], $hour[1]);
            $dayZero = $start->setTime($hour[0], $hour[1]);
            $rescue = $start->modify('+48 hours');
            $suffix = $start->format('Y-m-d');

            if ($now >= $dayBefore && $now < $dayZero) {
                $this->emitConfigured($user, $config, 'ingreso_dia_menos_1', 'income:-1:' . $suffix, [], $period, false);
            } elseif ($now >= $dayZero && $now < $rescue) {
                $this->emitConfigured($user, $config, 'ingreso_dia_0', 'income:0:' . $suffix, [], $period, $pushAllowed && !$daily);
            } elseif ($now >= $rescue) {
                $this->emitConfigured($user, $config, 'ingreso_rescate', 'income:r:' . $suffix, [], $period, $pushAllowed && !$daily);
            }
        }
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, array<string, mixed>> $config
     */
    private function categoryAlerts(array $user, array $config, bool $pushAllowed): void
    {
        if (empty($config['alerta_categoria']['activo'])) {
            return;
        }

        $support = new FinanceSupportService();
        $period = $support->period($user);
        $gastos = new GastoRepository();
        $userId = (int) $user['id'];
        $alerts80 = [];
        $alerts100 = [];

        foreach ($support->categories($user) as $category) {
            $name = (string) $category['nombre'];
            $assigned = $gastos->sumAssignedForPart($userId, $name, $period['inicio'], $period['fin']);

            if ($assigned <= 0) {
                continue;
            }

            $spent = $gastos->sumSpentForPart($userId, $name, $period['inicio'], $period['fin']);
            $ratio = ($spent / $assigned) * 100;

            if ($ratio >= 80) {
                $key = 'cat:' . $period['inicio'] . ':' . $name . ':80';
                $created = $this->emit($user, 'alerta_categoria', $key, [
                    ':category' => $name,
                    ':percent' => '80',
                ], $period, false, null, '/categorias/ver?parte=' . rawurlencode($name));

                if ($created !== null) {
                    $alerts80[] = $created;
                }
            }

            if ($ratio >= 100) {
                $key = 'cat:' . $period['inicio'] . ':' . $name . ':100';
                $this->emit($user, 'alerta_categoria', $key, [
                    ':category' => $name,
                    ':percent' => '100',
                ], $period, $pushAllowed, null, '/categorias/ver?parte=' . rawurlencode($name));
                $alerts100[] = $name;
            }
        }

        if ($alerts80 === []) {
            return;
        }

        if (!$pushAllowed) {
            foreach ($alerts80 as $alert) {
                $this->repo->markPushHandled((int) $alert['id']);
            }

            return;
        }

        if (count($alerts80) === 1) {
            $alert = $alerts80[0];
            (new PushService())->send((int) $user['id'], $alert['title'], $alert['body'], $alert['url']);
            $this->repo->markPushHandled((int) $alert['id']);

            return;
        }

        $categories = array_map(static fn (array $alert): string => $alert['category'], $alerts80);
        $title = __('notifications.group.alerts_80.title');
        $body = __('notifications.group.alerts_80.body', [
            ':count' => (string) count($alerts80),
            ':categories' => implode(', ', $categories),
        ]);
        (new PushService())->send((int) $user['id'], $title, $body, '/inicio');

        foreach ($alerts80 as $alert) {
            $this->repo->markPushHandled((int) $alert['id']);
        }
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, array<string, mixed>> $config
     */
    private function monthlySummary(array $user, array $config, bool $pushAllowed): void
    {
        $summary = (new SummaryService())->dueFor($user);

        if ($summary === null) {
            return;
        }

        $periodStart = (string) $summary['periodo_inicio'];
        $periodEnd = (string) $summary['periodo_fin'];

        try {
            (new GamificationService())->onPeriodClosed($user, [
                'inicio' => $periodStart,
                'fin' => $periodEnd,
            ]);
        } catch (Throwable $exception) {
            Logger::warning('No se pudo actualizar el nivel al cerrar el período', [
                'user_id' => $user['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            (new NetWorthService())->snapshotForPeriod($user, $periodStart);
        } catch (Throwable $exception) {
            Logger::warning('No se pudo guardar el snapshot de patrimonio', [
                'user_id' => $user['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            (new ScoreService())->snapshotForPeriod($user, $periodStart);
        } catch (Throwable $exception) {
            Logger::warning('No se pudo guardar el snapshot del score', [
                'user_id' => $user['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }

        if (empty($config['resumen_mensual']['activo'])) {
            return;
        }

        $key = 'summary:' . $periodStart;
        $label = format_date($periodStart) . ' – ' . format_date($periodEnd);

        $this->emit($user, 'resumen_mensual', $key, [':period' => $label], [
            'inicio' => $periodStart,
            'fin' => $periodEnd,
        ], $pushAllowed, (int) $summary['id'], '/resumen?id=' . (int) $summary['id']);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function hitos(array $user): void
    {
        $userId = (int) $user['id'];
        $db = Database::connection();
        $candidates = [];

        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM metas_ahorro WHERE user_id = ? AND es_emergencia = 0'
        );
        $stmt->execute([$userId]);

        if ((int) $stmt->fetchColumn() > 0) {
            $candidates[] = 'primera_meta';
        }

        if ((new SummaryRepository())->count($userId) > 0) {
            $candidates[] = 'primer_mes_completo';
        }

        $savings = new SavingsRepository();
        $metas = $savings->metasForUser($userId);
        $emergencyId = 0;
        $target = 0;

        foreach ($metas as $meta) {
            if ((int) $meta['es_emergencia'] === 1) {
                $emergencyId = (int) $meta['id'];
                $target = (int) $meta['objetivo_centavos'];
                break;
            }
        }

        if ($emergencyId > 0 && $target > 0) {
            $saved = $savings->sumContributions($userId, $emergencyId) - $savings->sumWithdrawals($userId, $emergencyId);

            if ($saved >= $target * 0.5) {
                $candidates[] = 'emergencia_50';
            }

            if ($saved >= $target) {
                $candidates[] = 'emergencia_100';
            }
        }

        $stmt = $db->prepare('SELECT periodos_consecutivos FROM rachas WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $streak = (int) $stmt->fetchColumn();

        if ($streak >= 30) {
            $candidates[] = 'racha_30';
        }

        $insert = $db->prepare(
            'INSERT IGNORE INTO hitos_usuario (user_id, tipo, fecha_logro, created_at, updated_at)
             VALUES (?, ?, CURDATE(), NOW(), NOW())'
        );

        foreach (array_unique($candidates) as $type) {
            $insert->execute([$userId, $type]);

            if ($insert->rowCount() === 0) {
                continue;
            }

            // Con la gamificación apagada el hito sigue siendo informativo,
            // pero no debe celebrarse ni acumular modales para el futuro.
            if ((int) ($user['gamificacion_activa'] ?? 1) === 0) {
                $celebrate = $db->prepare(
                    'UPDATE hitos_usuario SET celebrado_at = NOW(), updated_at = NOW()
                     WHERE user_id = ? AND tipo = ? AND celebrado_at IS NULL'
                );
                $celebrate->execute([$userId, $type]);
            }

            $this->emit($user, 'hito_' . $type, 'hito:' . $type, [], null, false, null, '/inicio');
        }
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, array<string, mixed>> $config
     * @param array{inicio: string, fin: string} $period
     */
    private function emitConfigured(
        array $user,
        array $config,
        string $type,
        string $key,
        array $params,
        array $period,
        bool $push
    ): void {
        if (empty($config[$type]['activo'])) {
            return;
        }

        $this->emit($user, $type, $key, $params, $period, $push);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, string> $params
     * @param array{inicio: string, fin: string}|null $period
     * @return array{id: int, title: string, body: string, url: string, category?: string}|null
     */
    private function emit(
        array $user,
        string $type,
        string $key,
        array $params,
        ?array $period,
        bool $push,
        ?int $referenceId = null,
        string $url = '/notificaciones'
    ): ?array {
        $tone = $this->toneFor($type, (string) ($user['tono_notificaciones'] ?? 'neutral'));
        $titleKey = 'notifications.templates.' . $type . '.title';
        $bodyKey = 'notifications.templates.' . $type . '.body';
        $title = __($titleKey, $params);
        $body = __($bodyKey, $params);

        if ($tone !== 'neutral') {
            $prefixKey = 'notifications.tones.' . $tone;
            $prefix = __($prefixKey);

            if ($prefix !== $prefixKey) {
                $body = $prefix . ' ' . $body;
            }
        }

        $id = $this->repo->create(
            (int) $user['id'],
            $type,
            $key,
            $title,
            $body,
            $period['inicio'] ?? null,
            $period['fin'] ?? null,
            $push ? 1 : 0,
            $referenceId
        );

        if ($id === null) {
            return null;
        }

        if ($push && $this->pushAllowed($user)) {
            (new PushService())->send((int) $user['id'], $title, $body, $url);
        }

        $this->repo->markPushHandled($id);

        return [
            'id' => $id,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'category' => $params[':category'] ?? '',
        ];
    }

    private function toneFor(string $type, string $tone): string
    {
        if (in_array($type, self::CRITICAL, true)) {
            return 'neutral';
        }

        return in_array($tone, ['neutral', 'motivacional', 'empatico'], true) ? $tone : 'neutral';
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function hour(string $value): array
    {
        $parts = explode(':', $value);
        $h = max(0, min(23, (int) ($parts[0] ?? 9)));
        $m = max(0, min(59, (int) ($parts[1] ?? 0)));

        return [$h, $m];
    }
}
