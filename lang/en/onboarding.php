<?php

declare(strict_types=1);

return [
    'progress' => 'Step :current of :total',
    'steps' => [
        'tutorial' => 'How it works',
        'simulador' => 'Simulator',
        'metodo' => 'Your method',
        'moneda' => 'Currency',
        'tipo-ingreso' => 'Your income',
        'configuracion' => 'Setup',
        'recurrencia' => 'Recurrence',
        'primer-ingreso' => 'First income',
        'notificaciones' => 'Reminders',
    ],
    'tutorial' => [
        'title' => 'How Cauce works',
        'start' => 'Get started',
        'slides' => [
            [
                'title' => 'Cauce splits your money with intention',
                'body' => 'Cauce takes your income and divides it using a budgeting method. You decide how much goes to each part and see the result before moving any money.',
            ],
            [
                'title' => 'One method, many ways to live it',
                'body' => 'Try different methods with your real amount. Compare how your money would be split and choose the one that fits your situation best.',
            ],
            [
                'title' => 'You are in control',
                'body' => 'Nothing moves without your confirmation. You can adjust percentages, switch methods or pause what does not work for you. Cauce only gives you structure.',
            ],
        ],
    ],
    'simulador' => [
        'heading' => 'Try your amount',
        'subtitle' => 'Enter what you receive and compare how each method would split it.',
        'amount_label' => 'How much do you receive per month?',
        'amount_hint' => 'This is just an example: you can change it whenever you want.',
        'recommended' => 'Recommended for you: :method',
        'recommended_badge' => 'Recommended',
        'cultural_note' => 'Inspired by the Jewish tithing tradition; a modern adaptation, not a religious precept.',
        'quiz_button' => 'Not sure which one to choose? Answer 3 questions',
        'quiz' => [
            'q1' => 'Is your income fixed or variable?',
            'q2' => 'Do you have debt you want to pay off first?',
            'q3' => 'What matters most to you right now?',
            'choose' => 'Choose an option',
            'submit' => 'See my recommendation',
            'p1' => [
                'fijo' => 'Fixed',
                'variable' => 'Variable',
                'mixto' => 'Mixed',
            ],
            'p2' => [
                'si' => 'Yes',
                'no' => 'No',
                'no_seguro' => 'Not sure',
            ],
            'p3' => [
                'ahorrar' => 'Save',
                'invertir' => 'Invest',
                'libertad' => 'Freedom to spend',
                'donar' => 'Give',
            ],
        ],
    ],
    'methods' => [
        '50_30_20' => [
            'name' => '50/30/20 rule',
            'desc' => 'Splits your income into needs, wants and savings.',
        ],
        'seis_sobres' => [
            'name' => 'Six envelopes',
            'desc' => 'Divides your income into envelopes with specific purposes.',
        ],
        'cinco_frascos' => [
            'name' => 'Five jars',
            'desc' => 'Splits income into five jars for spending, investing, saving and giving.',
        ],
        '70_20_10' => [
            'name' => '70/20/10 rule',
            'desc' => 'Sends 70% to expenses, 20% to savings and 10% to debt or investing.',
        ],
        'kakebo' => [
            'name' => 'Kakebo',
            'desc' => 'Japanese method for mindful spending by category.',
        ],
    ],
    'metodo' => [
        'heading' => 'Choose your method',
        'subtitle' => 'You can change it later. The percentages apply to your next income.',
        'choose' => 'Choose this method and continue',
        'recommended_badge' => 'Recommended for you',
    ],
    'moneda' => [
        'heading' => 'Which currency are you paid in?',
        'subtitle' => 'You will use it to record amounts and see your reports.',
        'mxn' => 'Mexican peso',
        'usd' => 'US dollar',
        'eur' => 'Euro',
    ],
    'tipo_ingreso' => [
        'heading' => 'How does your income arrive?',
        'subtitle' => 'This helps us adjust the pace of your reminders.',
        'fijo' => 'Fixed',
        'fijo_hint' => 'Same amount every period',
        'variable' => 'Variable',
        'variable_hint' => 'Changes month to month',
        'mixto' => 'Mixed',
        'mixto_hint' => 'A fixed base plus extras',
    ],
    'configuracion' => [
        'heading' => 'How much do you want to set up now?',
        'subtitle' => 'You can complete the rest later from Settings.',
        'rapida' => [
            'title' => 'Quick setup',
            'desc' => 'Finish now with the essentials. Ideal if you want to start using Cauce right away.',
        ],
        'completa' => [
            'title' => 'Full setup',
            'desc' => 'Also define your income recurrence to sharpen your reports.',
        ],
    ],
    'recurrencia' => [
        'heading' => 'How often do you receive your income?',
        'subtitle' => 'You can change it whenever you want.',
        'diaria' => 'Daily',
        'semanal' => 'Weekly',
        'mensual' => 'Monthly',
        'anual' => 'Yearly',
    ],
    'primer_ingreso' => [
        'heading' => 'Shall we record your first income?',
        'subtitle' => 'If you prefer, you can do it later from the + button.',
        'yes' => 'Yes, record it now',
        'no' => 'Later',
        'hint' => 'You can record it now or do it later from the + button.',
    ],
    'notificaciones' => [
        'heading' => 'A nudge when you need it',
        'subtitle' => 'Reminders help you keep your budget up to date. You decide when to turn them on.',
        'reason_1' => 'Gentle reminders to record your transactions.',
        'reason_2' => 'Alerts when your period is about to close.',
        'reason_3' => 'You can turn them off at any time.',
        'activate' => 'Turn on reminders',
        'not_now' => 'Not now',
        'hint' => 'Cauce never shares your data with third parties.',
        'status_active' => 'Reminders inside Cauce are on.',
        'status_push' => 'Reminders are on. We also enabled notifications on this device.',
        'status_denied' => 'Reminders inside Cauce are on. Browser notifications are blocked; you can change that in Settings.',
        'status_unsupported' => 'Reminders inside Cauce are on. This browser does not support push notifications.',
    ],
    'greetings' => [
        'morning' => 'Good morning',
        'afternoon' => 'Good afternoon',
        'evening' => 'Good evening',
    ],
    'flash' => [
        'completed' => 'Your onboarding is complete. Welcome to Cauce!',
    ],
    'errors' => [
        'amount' => 'Enter a valid amount.',
        'method' => 'Choose a method to continue.',
        'currency' => 'Choose a valid currency.',
        'income_type' => 'Choose your income type.',
        'configuration' => 'Choose a setup type.',
        'recurrence' => 'Choose a recurrence.',
        'quiz' => 'Answer all 3 questions to see your recommendation.',
        'generic' => 'An error occurred. Please try again.',
    ],
];
