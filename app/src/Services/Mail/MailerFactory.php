<?php

declare(strict_types=1);

namespace Cauce\Services\Mail;

/**
 * Selecciona el mailer según configuración.
 *
 * Por ahora solo existe NullMailer; el transporte SMTP se añadirá más adelante.
 */
final class MailerFactory
{
    private function __construct()
    {
    }

    public static function make(): MailerInterface
    {
        return new NullMailer();
    }
}
