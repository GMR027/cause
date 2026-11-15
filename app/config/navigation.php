<?php

declare(strict_types=1);

/*
 * Navegación única para sidebar, drawer y dock.
 *
 * Cada ítem: label (clave i18n), icon (Bootstrap Icons), route.
 */

return [
    'groups' => [
        [
            'key' => 'quick',
            'label' => 'nav.groups.quick',
            'items' => [
                ['label' => 'nav.inicio', 'icon' => 'bi-house-door', 'route' => '/inicio'],
                ['label' => 'nav.movimientos', 'icon' => 'bi-cash-stack', 'route' => '/movimientos'],
                ['label' => 'nav.reportes', 'icon' => 'bi-bar-chart', 'route' => '/reportes'],
            ],
        ],
        [
            'key' => 'finanzas',
            'label' => 'nav.groups.finanzas',
            'items' => [
                ['label' => 'nav.ahorro', 'icon' => 'bi-piggy-bank', 'route' => '/ahorro'],
                ['label' => 'nav.deudas', 'icon' => 'bi-credit-card', 'route' => '/deudas'],
                ['label' => 'nav.deseos', 'icon' => 'bi-stars', 'route' => '/deseos'],
                ['label' => 'nav.inversion', 'icon' => 'bi-graph-up-arrow', 'route' => '/inversion'],
                ['label' => 'nav.donacion', 'icon' => 'bi-heart', 'route' => '/donacion'],
                // Solo aparece si el método activo tiene la categoría Compartir (p. ej. Cinco frascos).
                ['label' => 'nav.compartir', 'icon' => 'bi-people', 'route' => '/compartir', 'requires_category' => 'compartir'],
            ],
        ],
        [
            'key' => 'herramientas',
            'label' => 'nav.groups.herramientas',
            'items' => [
                ['label' => 'nav.calendario', 'icon' => 'bi-calendar3', 'route' => '/calendario'],
                ['label' => 'nav.resumen', 'icon' => 'bi-journal-text', 'route' => '/resumen'],
                ['label' => 'nav.estado_cuenta', 'icon' => 'bi-file-earmark-text', 'route' => '/estado-cuenta', 'desktop_only' => true],
                ['label' => 'nav.notificaciones', 'icon' => 'bi-bell', 'route' => '/notificaciones'],
                ['label' => 'nav.soporte', 'icon' => 'bi-life-preserver', 'route' => '/soporte'],
                ['label' => 'nav.tags', 'icon' => 'bi-tags', 'route' => '/ajustes/tags'],
                ['label' => 'nav.subcategorias', 'icon' => 'bi-diagram-3', 'route' => '/subcategorias'],
            ],
        ],
        [
            'key' => 'configuracion',
            'label' => 'nav.groups.configuracion',
            'items' => [
                ['label' => 'nav.ajustes', 'icon' => 'bi-sliders', 'route' => '/ajustes'],
                ['label' => 'nav.seguridad', 'icon' => 'bi-shield-lock', 'route' => '/ajustes/seguridad'],
                ['label' => 'nav.privacidad', 'icon' => 'bi-incognito', 'route' => '/ajustes/privacidad'],
                ['label' => 'nav.acerca_de', 'icon' => 'bi-info-circle', 'route' => '/acerca-de'],
            ],
        ],
        [
            'key' => 'admin',
            'label' => 'nav.groups.admin',
            'admin' => true,
            'items' => [
                ['label' => 'nav.admin_panel', 'icon' => 'bi-speedometer2', 'route' => '/admin'],
                ['label' => 'nav.admin_usuarios', 'icon' => 'bi-people', 'route' => '/admin/usuarios'],
                ['label' => 'nav.admin_soporte', 'icon' => 'bi-life-preserver', 'route' => '/admin/soporte'],
                ['label' => 'nav.admin_contenido', 'icon' => 'bi-collection', 'route' => '/admin/contenido'],
                ['label' => 'nav.admin_notificaciones', 'icon' => 'bi-bell-square', 'route' => '/admin/notificaciones'],
                ['label' => 'nav.admin_app', 'icon' => 'bi-phone', 'route' => '/admin/app'],
                ['label' => 'nav.admin_seguridad', 'icon' => 'bi-shield-check', 'route' => '/admin/seguridad'],
                ['label' => 'nav.admin_auditoria', 'icon' => 'bi-clipboard-data', 'route' => '/admin/auditoria'],
                ['label' => 'nav.admin_reportes', 'icon' => 'bi-file-bar-graph', 'route' => '/admin/reportes'],
                ['label' => 'nav.admin_legal', 'icon' => 'bi-file-earmark-ruled', 'route' => '/admin/legal'],
                ['label' => 'nav.admin_monedas', 'icon' => 'bi-currency-exchange', 'route' => '/admin/monedas'],
            ],
        ],
    ],

    'dock' => [
        ['label' => 'nav.inicio', 'icon' => 'bi-house-door', 'route' => '/inicio'],
        ['label' => 'nav.movimientos', 'icon' => 'bi-cash-stack', 'route' => '/movimientos'],
        ['label' => 'nav.reportes', 'icon' => 'bi-bar-chart', 'route' => '/reportes'],
    ],

    'fab' => [
        ['label' => 'nav.fab.ingreso', 'icon' => 'bi-arrow-down-circle', 'route' => '/ingresos/nuevo'],
        ['label' => 'nav.fab.gasto', 'icon' => 'bi-arrow-up-circle', 'route' => '/gastos/nuevo'],
        ['label' => 'nav.fab.mover', 'icon' => 'bi-arrow-left-right', 'route' => '/mover'],
        ['label' => 'nav.fab.aportar', 'icon' => 'bi-piggy-bank', 'route' => '/aportar', 'phase' => 4],
    ],

    'placeholders' => [
        '/aportar' => ['title' => 'nav.fab.aportar', 'phase' => 4],
        '/admin/notificaciones' => ['title' => 'nav.admin_notificaciones', 'phase' => 4, 'admin' => true],
        '/admin/app' => ['title' => 'nav.admin_app', 'phase' => 4, 'admin' => true],
        '/admin/seguridad' => ['title' => 'nav.admin_seguridad', 'phase' => 4, 'admin' => true],
    ],
];
