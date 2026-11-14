<?php

declare(strict_types=1);

return [
    'title' => 'Giving',
    'subtitle' => 'Giving is part of the plan too. You choose how much and to which cause.',
    'badge' => 'Giving',
    'card' => [
        'title' => 'Giving',
        'hint' => 'Given this period',
        'period' => ':percent% of the assigned amount',
    ],
    'actions' => [
        'donate' => 'Donate',
        'activate' => 'Activate giving',
    ],
    'summary' => [
        'period' => 'Given this period',
        'period_hint' => 'Only normal donations reduce the %.',
        'total' => 'All-time total',
        'total_hint' => 'All your recorded donations.',
        'available_hint' => ':percent% of the method · :assigned assigned',
    ],
    'by_cause' => 'By cause',
    'no_causes' => 'You have not set any causes yet.',
    'history' => 'History',
    'empty' => 'No donations yet.',
    'cause' => 'Cause (optional)',
    'cause_placeholder' => 'E.g. animal shelter',
    'extra_label' => 'This donation is extra, not from the method %',
    'extra_hint' => 'Extra donations do not reduce the period’s available amount.',
    'extra_short' => 'Extra',
    'no_cause' => 'No cause',
    'no_category_title' => 'Your method does not include giving',
    'no_category_body' => 'You can activate a giving category or record only extra donations.',
    'activate_hint' => 'Giving is taken from the largest controlled category. The change applies to future income.',
    'activate_label' => 'Giving %',
    'activate_future' => 'A new version of your method will be created from now on.',
    'edit' => 'Edit donation',
    'flash' => [
        'donated' => 'Donation recorded.',
        'activated' => 'Giving activated for future income.',
        'deactivated' => 'Giving deactivated for future income.',
        'updated' => 'Donation updated.',
        'deleted' => 'Donation deleted.',
    ],
    'celebration' => [
        'thanks' => [
            'Thank you for your generosity!',
            'What a great gesture!',
            'Your help is on its way',
            'You make a difference!',
            'Giving is growing too',
            'A generous heart!',
        ],
        'body' => 'You donated :amount. That makes :total across :count donations.',
        'body_one' => 'You donated :amount. The start of something beautiful!',
        'record_title' => 'New record!',
        'records' => [
            'first' => 'Your first donation',
            'biggest' => 'Your biggest donation',
            'best_month' => 'Your best month of giving',
            'milestone' => ':count donations recorded',
        ],
        'close' => 'Keep going',
        'dismiss' => 'Close',
    ],
    'errors' => [
        'no_category' => 'Your method has no active giving. Activate it or record an extra donation.',
        'invalid_percent' => 'Enter a valid giving percentage.',
    ],
];
