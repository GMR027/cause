<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('PUSH_ENABLED', false),
    'public_key' => (string) env('VAPID_PUBLIC_KEY', ''),
    'private_key' => (string) env('VAPID_PRIVATE_KEY', ''),
    'subject' => (string) env('VAPID_SUBJECT', 'https://cauce.app'),
];
