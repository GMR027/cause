<?php

declare(strict_types=1);

return [
    'amount' => 'Amount',
    'date' => 'Date',
    'reason' => 'Reason',
    'reason_text' => 'Detail (optional)',
    'available' => 'Available',
    'over_percentage' => 'Contribution above the %',
    'confirm_delete' => 'Delete this movement?',
    'flash' => [
        'over_percentage' => 'The movement exceeds the assigned % for the period. It was marked as a contribution above the %.',
    ],
    'errors' => [
        'closed' => 'You can only edit movements in the current period.',
        'withdraw_exceeds' => 'The withdrawal exceeds the available accumulated amount.',
        'inactive_meta' => 'You cannot contribute to an inactive goal.',
        'meta_not_found' => 'The goal does not exist or does not belong to you.',
        'movement_not_found' => 'The movement does not exist or does not belong to you.',
        'emergency_locked' => 'The emergency fund cannot be deactivated.',
        'emergency_not_found' => 'The emergency fund does not exist.',
        'emergency_edit' => 'The emergency fund is updated from its own button.',
        'date' => 'Choose a valid date.',
        'negative' => 'The adjustment would leave the balance negative.',
        'amount' => 'Enter an amount greater than zero.',
        'reason' => 'Choose a valid reason.',
        'percentage' => 'The percentage is not valid.',
        'generic' => 'Could not save the movement.',
    ],
];
