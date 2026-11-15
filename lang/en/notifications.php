<?php

declare(strict_types=1);

return [
    'push_open' => 'View',
    'dismiss' => 'Dismiss notification',
    'title' => 'Notifications',
    'subtitle' => 'Useful reminders so nothing important slips by.',
    'badge' => 'Reminders',
    'empty' => 'You have no notifications.',
    'empty_hint' => 'Income reminders, spending alerts and summaries will show up here.',
    'mark_all' => 'Mark all as read',
    'unread' => ':count unread',
    'unread_one' => '1 unread',
    'new' => 'New',
    'open' => 'Open',
    'view_settings' => 'Notification settings',
    'types' => [
        'ingreso_dia_menos_1' => 'Income reminder (day before)',
        'ingreso_dia_0' => 'Income reminder (start day)',
        'ingreso_rescate' => 'Missing income notice',
        'gasto_recurrente' => 'Recurring charge',
        'recordatorio_deuda' => 'Debt reminder',
        'recordatorio_suscripcion' => 'Subscription reminder',
        'fin_prueba_suscripcion' => 'Free trial ending',
        'soporte_respuesta' => 'Support reply',
        'alerta_categoria' => 'Category near its limit',
        'resumen_mensual' => 'Monthly summary',
    ],
    'settings' => [
        'title' => 'Notifications',
        'subtitle' => 'Choose what you receive, when, and in which tone.',
        'config_title' => 'Reminders and times',
        'config_hint' => 'Income reminders use the time you set. Recurring expenses are announced when they are recorded.',
        'active' => 'Active',
        'hour' => 'Time',
        'frequency' => 'Frequency',
        'save' => 'Save settings',
        'frequencies' => [
            'una_vez' => 'Once',
            'diaria' => 'Daily',
            'semanal' => 'Weekly',
            'mensual' => 'Monthly',
        ],
        'tone_title' => 'Reminder tone',
        'tone_hint' => 'Critical reminders always use a neutral tone.',
        'tones' => [
            'neutral' => 'Neutral',
            'motivacional' => 'Motivational',
            'empatico' => 'Empathetic',
        ],
        'tone_save' => 'Save tone',
        'vacation_title' => 'Vacation mode',
        'vacation_hint' => 'Pause Web Push for 7 to 30 days. In-app reminders stay available. When it ends, we tell you once.',
        'vacation_active' => 'Push paused until :date.',
        'vacation_none' => 'No active pause.',
        'pause_7' => '7 days',
        'pause_15' => '15 days',
        'pause_30' => '30 days',
        'pause' => 'Pause',
        'resume' => 'Resume now',
        'push_title' => 'Web Push',
        'push_hint' => 'Turn on reminders on this device. We only send actionable or urgent items.',
        'push_enabled' => 'Web Push is configured.',
        'push_disabled' => 'Web Push is not configured on the server yet. Reminders stay in the app and in storage/logs/push.log.',
        'push_activate' => 'Enable on this device',
        'push_subscribed' => ':count subscribed device(s).',
        'push_ok' => 'Device subscribed to reminders.',
        'push_error' => 'Could not enable push.',
        'push_denied' => 'The browser blocked notifications.',
        'push_unsupported' => 'This browser does not support Web Push.',
    ],
    'flash' => [
        'config_saved' => 'Notification settings saved.',
        'tone_saved' => 'Tone updated.',
        'vacation_saved' => 'Vacation mode enabled.',
        'vacation_resumed' => 'Push reminders resumed.',
    ],
    'errors' => [
        'tone' => 'Choose a valid tone.',
        'vacation_days' => 'Choose a valid vacation duration.',
        'config' => 'Check the times and frequencies: they are not valid.',
    ],
    'tones' => [
        'motivacional' => 'Nice work!',
        'empatico' => 'No pressure.',
    ],
    'group' => [
        'alerts_80' => [
            'title' => 'Several categories at 80%',
            'body' => ':count categories are close to their limit: :categories.',
        ],
    ],
    'templates' => [
        'recordatorio_suscripcion' => [
            'title' => ':name will be charged',
            'body' => 'On :date you will be charged :amount for :name. If you no longer use it, cancel it from Subscriptions.',
        ],
        'fin_prueba_suscripcion' => [
            'title' => 'The :name trial is ending',
            'body' => 'Your free trial ends on :date; after that :amount will be charged. Cancel before if you do not want it.',
        ],
        'ingreso_dia_menos_1' => [
            'title' => 'Your income arrives tomorrow',
            'body' => 'Your period starts tomorrow. Have the amount ready to record it on time.',
        ],
        'ingreso_dia_0' => [
            'title' => 'Time to record your income',
            'body' => 'Your new period has started. Record the income so you can split it with your method.',
        ],
        'ingreso_rescate' => [
            'title' => 'Your period still has no income',
            'body' => '48 hours have passed since the start. Record your income to stay on track.',
        ],
        'gasto_recurrente' => [
            'title' => 'Recurring charge: :name',
            'body' => 'We recorded :amount in :part. Check that everything looks right.',
        ],
        'recordatorio_deuda' => [
            'title' => 'Payment reminder: :name',
            'body' => 'It is time to pay :amount for :name. Record the payment from Debts.',
        ],
        'soporte_respuesta' => [
            'title' => 'Support reply: :subject',
            'body' => ':preview',
        ],
        'alerta_categoria' => [
            'title' => 'Category near its limit',
            'body' => 'You have used :percent% of :category. Adjust or move funds before going over.',
        ],
        'resumen_mensual' => [
            'title' => 'Your period summary is ready',
            'body' => 'Review :period and write your reflection.',
        ],
        'vacaciones_reactivacion' => [
            'title' => 'Your push reminders are active again',
            'body' => 'The pause ended. We are still looking after your method.',
        ],
        'hito_primera_meta' => [
            'title' => 'First goal created!',
            'body' => 'You have a savings goal now. One step at a time.',
        ],
        'hito_primer_mes_completo' => [
            'title' => 'First full month',
            'body' => 'You closed your first period with Cauce. Your summary is ready.',
        ],
        'hito_emergencia_50' => [
            'title' => 'Emergency fund at 50%',
            'body' => 'You have half of your cushion. Keep going.',
        ],
        'hito_emergencia_100' => [
            'title' => 'Emergency fund complete',
            'body' => 'You completed your fund. Breathe a little easier now.',
        ],
        'hito_racha_30' => [
            'title' => '30 periods of consistency',
            'body' => 'Thirty periods of tracking. Consistency is the method.',
        ],
    ],
];
