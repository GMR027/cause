<?php

declare(strict_types=1);

return [
    'title' => 'Sharing',
    'subtitle' => 'What you share with family, friends or as gifts, based on your method’s Sharing %.',
    'badge' => 'Sharing',
    'actions' => [
        'create' => 'Record',
    ],
    'summary' => [
        'period' => 'Shared this period',
        'progress' => ':percent % of your Sharing percentage',
        'completed' => 'Sharing percentage met',
        'total' => 'All-time total',
        'total_hint' => 'Everything you have shared.',
        'available_hint' => ':percent % of the method · :assigned assigned',
    ],
    'by_recipient' => 'With whom',
    'no_recipients' => 'You have not said who you share with yet.',
    'history' => 'History',
    'empty' => 'No Sharing records yet.',
    'recipient' => 'With whom? (optional)',
    'recipient_placeholder' => 'E.g. dinner with my family',
    'no_recipient' => 'No recipient',
    'extra_label' => 'This is extra, not from the method %',
    'extra_hint' => 'Extra amounts do not count against your Sharing balance.',
    'extra_short' => 'Extra',
    'edit' => 'Edit record',
    'no_category_title' => 'Your method has no Sharing category',
    'no_category_body' => 'This section applies to methods with a Sharing category, such as Five jars. You can still record extra amounts.',
    'flash' => [
        'created' => 'Sharing record saved.',
        'updated' => 'Sharing record updated.',
        'deleted' => 'Sharing record deleted.',
    ],
    'errors' => [
        'no_category' => 'Your method has no Sharing category. Record it as extra.',
        'not_found' => 'That record does not exist.',
    ],
];
