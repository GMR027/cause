<?php

declare(strict_types=1);

return [
    'title' => 'Resúmenes mensuales',
    'subtitle' => 'Un cierre honesto de cada período, con espacio para tu reflexión.',
    'badge' => 'Resumen',
    'period' => 'Período :range',
    'generated' => 'Generado el :date',
    'view' => 'Ver resumen',
    'empty' => 'Todavía no hay resúmenes.',
    'empty_hint' => 'Se generará automáticamente al iniciar tu siguiente período.',
    'back' => 'Volver a resúmenes',
    'immutable' => 'Este resumen queda congelado al cierre del período. Tu reflexión sí se puede editar.',
    'metrics' => [
        'income' => 'Ingreso',
        'expense' => 'Gastos',
        'savings' => 'Ahorro neto',
        'investment' => 'Inversión neta',
        'donation' => 'Donación',
        'uncontrolled' => 'No controlado',
        'percent_spent' => ':percent % del ingreso gastado',
    ],
    'categories' => [
        'title' => 'Categorías',
        'assigned' => 'Asignado',
        'spent' => 'Gastado',
        'excess' => 'Exceso',
        'empty' => 'Sin actividad por categoría.',
    ],
    'moves' => [
        'title' => 'Movimientos entre categorías',
        'empty' => 'Sin movimientos entre categorías.',
    ],
    'withdrawals' => [
        'title' => 'Retiros',
        'empty' => 'Sin retiros.',
        'ahorro' => 'Ahorro',
        'inversion' => 'Inversión',
    ],
    'contributions' => [
        'title' => 'Aportaciones',
        'empty' => 'Sin aportaciones.',
    ],
    'reflection' => [
        'title' => 'Tu reflexión',
        'hint' => '¿Qué funcionó, qué ajustarías y qué quieres probar el próximo período?',
        'placeholder' => 'Escribe con calma. Solo tú lo verás.',
        'save' => 'Guardar reflexión',
    ],
    'flash' => [
        'reflection_saved' => 'Reflexión guardada.',
        'not_found' => 'El resumen no existe.',
        'error' => 'No se pudo guardar la reflexión.',
    ],
];
