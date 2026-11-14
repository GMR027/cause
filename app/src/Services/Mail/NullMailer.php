<?php

declare(strict_types=1);

namespace Cauce\Services\Mail;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

/**
 * Mailer nulo: no envía correos, solo los registra en storage/logs/mail.log.
 */
final class NullMailer implements MailerInterface
{
    public function send(string $to, string $subject, string $body, string $lang): bool
    {
        $path = base_path('storage/logs/mail.log');
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $handler = new StreamHandler($path, Level::Info);
        $handler->setFormatter(new LineFormatter(null, null, true, true));

        $logger = new Logger('mail');
        $logger->pushHandler($handler);
        $logger->info('Correo preparado (MAIL_ENABLED=false)', [
            'to' => $to,
            'subject' => $subject,
            'lang' => $lang,
            'body' => $body,
        ]);

        return true;
    }
}
