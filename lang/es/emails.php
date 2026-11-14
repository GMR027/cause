<?php

declare(strict_types=1);

/*
 * Plantillas de correo. NullMailer las registra en storage/logs/mail.log.
 */

return [
    'welcome' => [
        'subject' => 'Bienvenido a Cauce',
        'body' => "Hola :alias,\n\nTu cuenta de Cauce está lista. Guarda tu código de recuperación y configura tus preguntas de seguridad.\n\n— Equipo Cauce",
    ],
    'recovery_code' => [
        'subject' => 'Tu código de recuperación de Cauce',
        'body' => "Hola :alias,\n\nTu código de recuperación es: :code\n\nGuárdalo en un lugar seguro. Sin él y sin tus respuestas, no podrás recuperar tu cuenta.\n\n— Equipo Cauce",
    ],
    'password_reset' => [
        'subject' => 'Tu contraseña de Cauce cambió',
        'body' => "Hola :alias,\n\nTu contraseña se actualizó correctamente. Si no fuiste tú, contacta al administrador de inmediato.\n\n— Equipo Cauce",
    ],
    'admin_reset' => [
        'subject' => 'Acceso restablecido en Cauce',
        'body' => "Hola :alias,\n\nEl administrador generó un nuevo código de recuperación para tu cuenta. Solicítalo por el canal de soporte.\n\n— Equipo Cauce",
    ],
];
