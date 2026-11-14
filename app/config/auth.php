<?php

declare(strict_types=1);

/*
 * Configuración de autenticación, sesiones y recuperación.
 */

return [
    'session_name' => 'cauce_session',
    'session_lifetime' => 0,
    'recovery' => [
        'max_attempts' => 3,
        'lock_minutes' => 15,
        'verified_minutes' => 10,
        'code_length' => 16,
        'alphabet' => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
    ],
    'password' => [
        'min_length' => 8,
        'max_length' => 255,
    ],
];
