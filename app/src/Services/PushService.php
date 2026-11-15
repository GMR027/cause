<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\NotificationRepository;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Throwable;

/**
 * Envío de Web Push con VAPID. Si falta configuración, registra en push.log.
 */
final class PushService
{
    public function enabled(): bool
    {
        return (bool) config('push.enabled', false)
            && (string) config('push.public_key', '') !== ''
            && (string) config('push.private_key', '') !== '';
    }

    public function publicKey(): string
    {
        return (string) config('push.public_key', '');
    }

    public function send(int $userId, string $title, string $body, ?string $url = null): bool
    {
        $subscriptions = (new NotificationRepository())->subscriptionsForUser($userId);

        if ($subscriptions === []) {
            return false;
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url ?? '/notificaciones',
            // Textos de los botones de la notificación del sistema (ver sw.js).
            'openLabel' => __('notifications.push_open'),
            'closeLabel' => '✕ ' . __('common.actions.close'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!$this->enabled() || !class_exists(WebPush::class)) {
            $this->log($userId, $title, $body, $subscriptions, 'push deshabilitado o sin claves');

            return false;
        }

        try {
            // minishlink/web-push avisa de GMP/BCMath como notice; no debe
            // convertirse en excepción por el manejador global de errores.
            $previousLevel = error_reporting();
            error_reporting($previousLevel & ~(
                E_NOTICE | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED | E_WARNING | E_USER_WARNING
            ));

            try {
                $webPush = new WebPush([
                    'VAPID' => [
                        'subject' => (string) config('push.subject', 'https://cauce.app'),
                        'publicKey' => (string) config('push.public_key', ''),
                        'privateKey' => (string) config('push.private_key', ''),
                    ],
                ]);

                foreach ($subscriptions as $subscription) {
                    try {
                        $webPush->queueNotification(
                            Subscription::create([
                                'endpoint' => (string) $subscription['endpoint'],
                                'publicKey' => (string) $subscription['p256dh'],
                                'authToken' => (string) $subscription['auth'],
                            ]),
                            (string) $payload
                        );
                    } catch (Throwable $exception) {
                        $this->log($userId, $title, $body, [$subscription], $exception->getMessage());
                    }
                }

                $success = false;
                $repo = new NotificationRepository();

                foreach ($webPush->flush() as $report) {
                    if ($report->isSuccess()) {
                        $success = true;
                        continue;
                    }

                    if (method_exists($report, 'isSubscriptionExpired') && $report->isSubscriptionExpired()) {
                        $endpoint = method_exists($report, 'getEndpoint') ? (string) $report->getEndpoint() : '';

                        if ($endpoint !== '') {
                            $repo->deleteSubscription($userId, $endpoint);
                        }
                    }
                }

                if (!$success) {
                    $this->log($userId, $title, $body, $subscriptions, 'ningún envío exitoso');
                }

                return $success;
            } finally {
                error_reporting($previousLevel);
            }
        } catch (Throwable $exception) {
            $this->log($userId, $title, $body, $subscriptions, $exception->getMessage());

            return false;
        }
    }

    /**
     * @param list<array<string, mixed>> $subscriptions
     */
    private function log(int $userId, string $title, string $body, array $subscriptions, string $reason): void
    {
        $path = base_path('storage/logs/push.log');
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $handler = new StreamHandler($path, Level::Info);
        $handler->setFormatter(new LineFormatter(null, null, true, true));
        $logger = new Logger('push');
        $logger->pushHandler($handler);
        $logger->info('Push no entregado: ' . $reason, [
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'subscriptions' => count($subscriptions),
        ]);
    }
}
