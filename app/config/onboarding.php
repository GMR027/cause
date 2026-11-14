<?php

declare(strict_types=1);

/*
 * Configuración del onboarding.
 *
 * El monto inicial del simulador es configurable por entorno para poder
 * actualizarlo cada año sin tocar código.
 */

return [
    'default_income_cents' => (int) env('ONBOARDING_DEFAULT_INCOME_CENTS', 1000000),
    'max_income_cents' => 1000000000000,

    'steps' => [
        'tutorial',
        'simulador',
        'metodo',
        'moneda',
        'tipo-ingreso',
        'configuracion',
        'recurrencia',
        'primer-ingreso',
        'notificaciones',
    ],

    'simulator_methods' => [
        '50_30_20',
        'seis_sobres',
        'cinco_frascos',
        '70_20_10',
    ],

    'selectable_methods' => [
        '50_30_20',
        'seis_sobres',
        'cinco_frascos',
        '70_20_10',
    ],

    'currencies' => ['MXN', 'USD', 'EUR'],
    'income_types' => ['fijo', 'variable', 'mixto'],
    'recurrences' => ['diaria', 'semanal', 'mensual', 'anual'],
    'configurations' => ['rapida', 'completa'],

    'defaults' => [
        'moneda' => 'MXN',
        'tipo_ingreso' => 'fijo',
        'recurrencia' => 'mensual',
        'configuracion' => 'completa',
    ],
];
