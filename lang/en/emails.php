<?php

declare(strict_types=1);

/*
 * Email templates. NullMailer records them in storage/logs/mail.log.
 */

return [
    'welcome' => [
        'subject' => 'Welcome to Cauce',
        'body' => "Hi :alias,\n\nYour Cauce account is ready. Save your recovery code and set up your security questions.\n\n— The Cauce team",
    ],
    'recovery_code' => [
        'subject' => 'Your Cauce recovery code',
        'body' => "Hi :alias,\n\nYour recovery code is: :code\n\nKeep it safe. Without it and without your answers, you will not be able to recover your account.\n\n— The Cauce team",
    ],
    'password_reset' => [
        'subject' => 'Your Cauce password changed',
        'body' => "Hi :alias,\n\nYour password was updated successfully. If this was not you, contact the administrator immediately.\n\n— The Cauce team",
    ],
    'admin_reset' => [
        'subject' => 'Access restored in Cauce',
        'body' => "Hi :alias,\n\nThe administrator generated a new recovery code for your account. Request it through the support channel.\n\n— The Cauce team",
    ],
];
