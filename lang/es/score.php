<?php

declare(strict_types=1);

return [
    'title' => 'Salud financiera',
    'home_label' => 'Tu salud financiera',
    'summary' => 'Tu salud financiera: :score/100',
    'score_value' => ':score/100',
    'no_data' => 'Aún no hay datos suficientes para calcular tu score.',
    'no_data_hint' => 'El score aparece cuando hay ingreso en el período y al menos un período anterior con datos de tu método.',
    'disclaimer' => 'El score es una guía, no un diagnóstico financiero.',
    'private' => 'Privado. No se comparte.',
    'detail_title' => 'Detalle del score',
    'breakdown_title' => 'Componentes',
    'weight' => 'Peso :weight%',
    'points' => ':points / :weight pts',
    'evolution_title' => 'Evolución del score',
    'evolution_empty' => 'Aún no hay historial suficiente. El primer snapshot se guarda este mes.',
    'month' => 'Mes',
    'view_detail' => 'Ver detalle',
    'trend' => [
        'up' => 'Vas mejorando',
        'down' => 'Revisa tu progreso',
        'same' => 'Se mantiene',
    ],
    'components' => [
        'metodo' => 'Cumplimiento del método',
        'ahorro' => 'Ahorro',
        'deudas' => 'Deudas',
        'emergencia' => 'Fondo de emergencia',
        'patrimonio' => 'Patrimonio neto',
    ],
    'reasons' => [
        'sin_ingreso' => 'No hay ingreso registrado en el período actual.',
        'sin_metodo' => 'Todavía no hay períodos anteriores con datos de tu método.',
    ],
];
