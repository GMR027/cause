<?php

declare(strict_types=1);

/*
 * Configuración de seguridad, privacidad y límites de Fase 8.
 */

return [
    // App Android (Tauri): paquete y huella SHA-256 del certificado de firma
    // (~/.config/cauce-android). Sirven para /.well-known/assetlinks.json y
    // para aceptar llaves de acceso (huella) creadas dentro de la app.
    'android' => [
        'package' => 'mx.com.iguzman.cauce',
        'cert_sha256' => (string) env('ANDROID_CERT_SHA256', 'eda3a39d5904622c15e54e987f3922b02c85b83ee025d5d39b3f12c08ff67d21'),
    ],
    'session' => [
        'idle_minutes' => 720,
        'absolute_days' => 30,
    ],
    'pin' => [
        'length' => 6,
        'max_attempts' => 5,
        'lock_minutes' => 15,
        'unlock_minutes' => 15,
    ],
    'login' => [
        'max_attempts' => 5,
        'window_minutes' => 15,
    ],
    'statements' => [
        'max_bytes' => 10485760,
        'max_files' => 240,
        'reauth_minutes' => 15,
        'consent_version' => '2026-11-13',
    ],
    'analytics' => [
        'retention_days' => 365,
    ],
    'export' => [
        'token_hours' => 72,
    ],
    'admin_stats' => [
        'cache_seconds' => 3600,
        'min_group' => 10,
    ],
];
