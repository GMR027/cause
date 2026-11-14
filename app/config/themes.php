<?php

declare(strict_types=1);

/*
 * Tema visual: paleta curada, modos y valores por defecto.
 *
 * El color personalizado se valida con WCAG antes de guardarse.
 */

return [
    'defaults' => [
        'modo' => 'auto',
        'color' => '#157f6b',
        'enfoque' => 'mixto',
        'fondo' => 'ninguno',
        'textura' => 'dots',
    ],

    'palette' => [
        '#157f6b',
        '#1d6f42',
        '#1565c0',
        '#3949ab',
        '#6a1b9a',
        '#ad1457',
        '#c62828',
        '#bf360c',
        '#6d4c41',
        '#37474f',
    ],

    'min_contrast' => 4.5,

    'modes' => ['claro', 'oscuro', 'auto'],
    'focus' => ['iconos', 'botones', 'mixto'],
    'backgrounds' => ['ninguno', 'radial', 'textura', 'radial_textura'],
    'textures' => ['dots', 'grid', 'waves'],
];
