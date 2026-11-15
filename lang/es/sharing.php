<?php

declare(strict_types=1);

return [
    'title' => 'Compartir',
    'subtitle' => 'Lo que compartes con familia, amigos o en regalos, según el % de Compartir de tu método.',
    'badge' => 'Compartir',
    'actions' => [
        'create' => 'Registrar',
    ],
    'summary' => [
        'period' => 'Compartido este período',
        'progress' => ':percent % del porcentaje de Compartir',
        'completed' => 'Porcentaje de Compartir cumplido',
        'total' => 'Total histórico',
        'total_hint' => 'Todo lo que has compartido.',
        'available_hint' => ':percent % del método · :assigned asignados',
    ],
    'by_recipient' => 'Con quién',
    'no_recipients' => 'Aún no has indicado con quién compartes.',
    'history' => 'Historial',
    'empty' => 'Todavía no hay registros de Compartir.',
    'recipient' => '¿Con quién? (opcional)',
    'recipient_placeholder' => 'Ej. comida con mi familia',
    'no_recipient' => 'Sin destinatario',
    'extra_label' => 'Es extra, no del % del método',
    'extra_hint' => 'Lo extra no descuenta del disponible de Compartir.',
    'extra_short' => 'Extra',
    'edit' => 'Editar registro',
    'no_category_title' => 'Tu método no incluye Compartir',
    'no_category_body' => 'Esta sección aplica a métodos con una categoría de Compartir, como Cinco frascos. Puedes registrar solo montos extra.',
    'flash' => [
        'created' => 'Registro de Compartir guardado.',
        'updated' => 'Registro de Compartir actualizado.',
        'deleted' => 'Registro de Compartir eliminado.',
    ],
    'errors' => [
        'no_category' => 'Tu método no tiene la categoría Compartir. Regístralo como extra.',
        'not_found' => 'Ese registro no existe.',
    ],
];
