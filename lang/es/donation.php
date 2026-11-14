<?php

declare(strict_types=1);

return [
    'title' => 'Donación',
    'subtitle' => 'Dar también es parte del plan. Tú eliges cuánto y a qué causa.',
    'badge' => 'Donación',
    'card' => [
        'title' => 'Donación',
        'hint' => 'Donado este período',
        'period' => ':percent % de lo asignado',
    ],
    'actions' => [
        'donate' => 'Donar',
        'activate' => 'Activar donación',
    ],
    'summary' => [
        'period' => 'Donado este período',
        'period_hint' => 'Solo donaciones normales descuentan del %.',
        'total' => 'Total histórico',
        'total_hint' => 'Todas tus donaciones registradas.',
        'available_hint' => ':percent % del método · :assigned asignados',
    ],
    'by_cause' => 'Por causa',
    'no_causes' => 'Aún no has indicado causas.',
    'history' => 'Historial',
    'empty' => 'Todavía no hay donaciones.',
    'cause' => 'Causa (opcional)',
    'cause_placeholder' => 'Ej. refugio de animales',
    'extra_label' => 'Esta donación es extra, no del % del método',
    'extra_hint' => 'Las donaciones extra no descuentan del disponible del período.',
    'extra_short' => 'Extra',
    'no_cause' => 'Sin causa',
    'no_category_title' => 'Tu método no incluye donación',
    'no_category_body' => 'Puedes activar una categoría de donación o registrar solo donaciones extra.',
    'activate_hint' => 'La donación se toma de la mayor categoría controlada. El cambio aplica a ingresos futuros.',
    'activate_label' => '% de donación',
    'activate_future' => 'Se creará una nueva versión de tu método desde ahora.',
    'edit' => 'Editar donación',
    'flash' => [
        'donated' => 'Donación registrada.',
        'activated' => 'Donación activada para ingresos futuros.',
        'deactivated' => 'Donación desactivada para ingresos futuros.',
        'updated' => 'Donación actualizada.',
        'deleted' => 'Donación eliminada.',
    ],
    'celebration' => [
        'thanks' => [
            '¡Gracias por tu generosidad!',
            '¡Qué gran gesto!',
            'Tu ayuda ya está en camino',
            '¡Haces la diferencia!',
            'Dar también es crecer',
            '¡Un corazón generoso!',
        ],
        'body' => 'Donaste :amount. Llevas :total en :count donaciones.',
        'body_one' => 'Donaste :amount. ¡Es el comienzo de algo bonito!',
        'record_title' => '¡Nuevo récord!',
        'records' => [
            'first' => 'Tu primera donación',
            'biggest' => 'Tu donación más grande',
            'best_month' => 'Tu mejor mes donando',
            'milestone' => ':count donaciones registradas',
        ],
        'close' => 'Seguir',
        'dismiss' => 'Cerrar',
    ],
    'errors' => [
        'no_category' => 'Tu método no tiene donación activa. Actívala o registra una donación extra.',
        'invalid_percent' => 'Ingresa un porcentaje de donación válido.',
    ],
];
