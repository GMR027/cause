<?php

declare(strict_types=1);

/*
 * Reglas de ingresos y asignaciones.
 */

return [
    'max_amount_cents' => 1000000000000,

    'types' => ['fijo', 'variable', 'extra'],

    'extra' => [
        // Fallback cuando el usuario no configuró un reparto propio.
        'fallback_types' => ['ahorro', 'inversion'],
        'percentage' => 100,
    ],

    'justification_reasons' => [
        'aun_no_transfiero',
        'lo_usare_luego',
        'efectivo',
        'otro',
    ],

    'states' => ['acreditado', 'justificado', 'no_controlado'],
];
