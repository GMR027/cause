<?php

declare(strict_types=1);

namespace Cauce\Services\Mail;

interface MailerInterface
{
    public function send(string $to, string $subject, string $body, string $lang): bool;
}
