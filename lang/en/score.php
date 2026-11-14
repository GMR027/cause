<?php

declare(strict_types=1);

return [
    'title' => 'Financial health',
    'home_label' => 'Your financial health',
    'summary' => 'Your financial health: :score/100',
    'score_value' => ':score/100',
    'no_data' => 'There is not enough data to calculate your score yet.',
    'no_data_hint' => 'The score appears when there is income in the period and at least one previous period with data from your method.',
    'disclaimer' => 'The score is a guide, not a financial diagnosis.',
    'private' => 'Private. Not shared.',
    'detail_title' => 'Score detail',
    'breakdown_title' => 'Components',
    'weight' => 'Weight :weight%',
    'points' => ':points / :weight pts',
    'evolution_title' => 'Score evolution',
    'evolution_empty' => 'Not enough history yet. The first snapshot is saved this month.',
    'month' => 'Month',
    'view_detail' => 'View detail',
    'trend' => [
        'up' => 'You are improving',
        'down' => 'Review your progress',
        'same' => 'It stays the same',
    ],
    'components' => [
        'metodo' => 'Method compliance',
        'ahorro' => 'Savings',
        'deudas' => 'Debts',
        'emergencia' => 'Emergency fund',
        'patrimonio' => 'Net worth',
    ],
    'reasons' => [
        'sin_ingreso' => 'There is no income recorded in the current period.',
        'sin_metodo' => 'There are no previous periods with data from your method yet.',
    ],
];
