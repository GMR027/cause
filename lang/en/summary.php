<?php

declare(strict_types=1);

return [
    'title' => 'Monthly summaries',
    'subtitle' => 'An honest close for every period, with room for your reflection.',
    'badge' => 'Summary',
    'period' => 'Period :range',
    'generated' => 'Generated on :date',
    'view' => 'View summary',
    'empty' => 'No summaries yet.',
    'empty_hint' => 'One will be generated automatically when your next period starts.',
    'back' => 'Back to summaries',
    'immutable' => 'This summary is frozen at the end of the period. Your reflection can still be edited.',
    'metrics' => [
        'income' => 'Income',
        'expense' => 'Expenses',
        'savings' => 'Net savings',
        'investment' => 'Net investment',
        'donation' => 'Donation',
        'uncontrolled' => 'Uncontrolled',
        'percent_spent' => ':percent% of income spent',
    ],
    'categories' => [
        'title' => 'Categories',
        'assigned' => 'Assigned',
        'spent' => 'Spent',
        'excess' => 'Excess',
        'empty' => 'No category activity.',
    ],
    'moves' => [
        'title' => 'Moves between categories',
        'empty' => 'No moves between categories.',
    ],
    'withdrawals' => [
        'title' => 'Withdrawals',
        'empty' => 'No withdrawals.',
        'ahorro' => 'Savings',
        'inversion' => 'Investment',
    ],
    'contributions' => [
        'title' => 'Contributions',
        'empty' => 'No contributions.',
    ],
    'reflection' => [
        'title' => 'Your reflection',
        'hint' => 'What worked, what would you adjust, and what do you want to try next period?',
        'placeholder' => 'Write at your own pace. Only you will see it.',
        'save' => 'Save reflection',
    ],
    'flash' => [
        'reflection_saved' => 'Reflection saved.',
        'not_found' => 'Summary not found.',
        'error' => 'Could not save the reflection.',
    ],
];
