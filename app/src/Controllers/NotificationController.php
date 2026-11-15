<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\View;
use Cauce\Repositories\NotificationRepository;
use Cauce\Repositories\UserRepository;
use Cauce\Services\AuthService;
use Cauce\Services\NotificationService;
use Cauce\Services\PushService;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Bandeja de notificaciones, configuración, tono y vacaciones.
 */
final class NotificationController
{
    private NotificationRepository $repo;

    public function __construct()
    {
        $this->repo = new NotificationRepository();
    }

    public function index(): void
    {
        $user = $this->user();
        $notifications = $this->repo->listForUser((int) $user['id'], 100);

        // Procesa pendientes para que la bandeja refleje el estado actual.
        try {
            (new NotificationService())->process($user);
            $notifications = $this->repo->listForUser((int) $user['id'], 100);
        } catch (Throwable) {
            // La bandeja sigue funcionando aunque el motor falle.
        }

        $unread = $this->repo->unreadCount((int) $user['id']);
        View::share(['unreadNotifications' => $unread]);

        echo View::render('notifications/index', [
            'title' => __('notifications.title'),
            'notifications' => $notifications,
            'unread' => $unread,
        ], 'layouts/app');
    }

    public function settings(): void
    {
        $user = $this->user();
        $config = $this->repo->configForUser((int) $user['id']);
        $push = new PushService();

        echo View::render('notifications/settings', [
            'title' => __('notifications.settings.title'),
            'config' => $config,
            'types' => array_keys($config),
            'tone' => (string) ($user['tono_notificaciones'] ?? 'neutral'),
            'vacation_until' => $user['push_pausado_hasta'] ?? null,
            'push_enabled' => $push->enabled(),
            'push_public_key' => $push->publicKey(),
            'subscriptions' => count($this->repo->subscriptionsForUser((int) $user['id'])),
        ], 'layouts/app');
    }

    public function updateConfig(): void
    {
        $user = $this->user();
        $active = is_array($_POST['activo'] ?? null) ? $_POST['activo'] : [];
        $hours = is_array($_POST['hora'] ?? null) ? $_POST['hora'] : [];
        $frequencies = is_array($_POST['frecuencia'] ?? null) ? $_POST['frecuencia'] : [];
        $allowed = ['una_vez', 'diaria', 'semanal', 'mensual'];
        $pending = [];

        foreach (array_keys($this->repo->configForUser((int) $user['id'])) as $type) {
            $hour = isset($hours[$type]) && is_string($hours[$type]) && $hours[$type] !== ''
                ? $hours[$type]
                : null;
            $frequency = isset($frequencies[$type]) && is_string($frequencies[$type])
                ? $frequencies[$type]
                : 'una_vez';

            if ($hour !== null && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hour) !== 1) {
                Flash::error('notifications.errors.config');
                redirect('/ajustes/notificaciones');
            }

            if (!in_array($frequency, $allowed, true)) {
                Flash::error('notifications.errors.config');
                redirect('/ajustes/notificaciones');
            }

            $pending[] = [(string) $type, !empty($active[$type]), $hour, $frequency];
        }

        foreach ($pending as [$type, $isActive, $hour, $frequency]) {
            $this->repo->updateConfig(
                (int) $user['id'],
                $type,
                $isActive,
                $hour,
                $frequency
            );
        }

        Flash::success('notifications.flash.config_saved');
        redirect('/ajustes/notificaciones');
    }

    public function updateTone(): void
    {
        $user = $this->user();
        $tone = (string) input('tono', 'neutral');

        if (!in_array($tone, ['neutral', 'motivacional', 'empatico'], true)) {
            Flash::error('notifications.errors.tone');
            redirect('/ajustes/notificaciones');
        }

        (new UserRepository())->updateTone((int) $user['id'], $tone);
        Flash::success('notifications.flash.tone_saved');
        redirect('/ajustes/notificaciones');
    }

    public function vacation(): void
    {
        $user = $this->user();
        $raw = trim((string) input('dias', '0'));

        if (!ctype_digit($raw) || !in_array((int) $raw, [0, 7, 15, 30], true)) {
            Flash::error('notifications.errors.vacation_days');
            redirect('/ajustes/notificaciones');
        }

        $days = (int) $raw;
        $until = null;

        if ($days > 0) {
            $until = (new DateTimeImmutable('+' . $days . ' days', new DateTimeZone('UTC')))
                ->format('Y-m-d H:i:s');
        }

        (new UserRepository())->setPushPausedUntil((int) $user['id'], $until);
        Flash::success($days === 0 ? 'notifications.flash.vacation_resumed' : 'notifications.flash.vacation_saved');
        redirect('/ajustes/notificaciones');
    }

    public function markRead(): void
    {
        $user = $this->user();
        $id = (int) input('id', '0');
        $all = input('todos') !== null;

        if ($all) {
            $this->repo->markAllRead((int) $user['id']);
        } elseif ($id > 0) {
            $this->repo->markRead($id, (int) $user['id']);
        }

        if ($this->wantsJson()) {
            $this->json(['ok' => true, 'unread' => $this->repo->unreadCount((int) $user['id'])]);
        }

        redirect('/notificaciones');
    }

    public function dismiss(): void
    {
        $user = $this->user();
        $id = (int) input('id', '0');

        if ($id > 0) {
            $this->repo->dismiss($id, (int) $user['id']);
        }

        if ($this->wantsJson()) {
            $this->json(['ok' => true, 'unread' => $this->repo->unreadCount((int) $user['id'])]);
        }

        redirect('/notificaciones');
    }

    public function subscribe(): void
    {
        $user = $this->user();
        $endpoint = trim((string) input('endpoint', ''));
        $p256dh = trim((string) input('p256dh', ''));
        $auth = trim((string) input('auth', ''));
        $scheme = strtolower((string) parse_url($endpoint, PHP_URL_SCHEME));

        if (
            $endpoint === ''
            || strlen($endpoint) > 1000
            || filter_var($endpoint, FILTER_VALIDATE_URL) === false
            || $scheme !== 'https'
            || preg_match('/^[A-Za-z0-9_\-+\/]{20,200}={0,2}$/', $p256dh) !== 1
            || preg_match('/^[A-Za-z0-9_\-+\/]{8,100}={0,2}$/', $auth) !== 1
        ) {
            $this->json(['ok' => false, 'error' => 'invalid'], 422);
        }

        $this->repo->saveSubscription(
            (int) $user['id'],
            $endpoint,
            $p256dh,
            $auth,
            isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null
        );

        $this->json(['ok' => true]);
    }

    public function unsubscribe(): void
    {
        $user = $this->user();
        $endpoint = (string) input('endpoint', '');

        if ($endpoint !== '') {
            $this->repo->deleteSubscription((int) $user['id'], $endpoint);
        }

        $this->json(['ok' => true]);
    }

    private function wantsJson(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
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
}
