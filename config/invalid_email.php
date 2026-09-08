<?php

return [
    /*
    | Progressive cooldown (hours) after permanent delivery failures.
    | Index 0 = first failure, 1 = second, 2 = third, 3+ = fourth and above.
    */
    'cooldowns_hours' => [
        (int) env('INVALID_EMAIL_COOLDOWN_1_HOURS', 24),
        (int) env('INVALID_EMAIL_COOLDOWN_2_HOURS', 72),
        (int) env('INVALID_EMAIL_COOLDOWN_3_HOURS', 168),
        (int) env('INVALID_EMAIL_COOLDOWN_4_HOURS', 720),
    ],

    'statuses' => [
        'active' => 'active',
        'temporarily_invalid' => 'temporarily_invalid',
        'permanently_blocked' => 'permanently_blocked',
        'verified' => 'verified',
    ],

    'failure_reasons' => [
        'address_not_found' => 'Address Not Found',
        'mailbox_does_not_exist' => 'Mailbox Does Not Exist',
        'permanent_delivery_failure' => 'Permanent Delivery Failure',
        'unable_to_receive_mail' => 'Unable to Receive Mail',
        'other' => 'Other',
    ],

    /*
    | Substrings that indicate a permanent recipient failure (case-insensitive).
    | Temporary network/SMTP issues must NOT match these.
    */
    'permanent_failure_markers' => [
        'address not found',
        'user unknown',
        'mailbox not found',
        'mailbox unavailable',
        'mailbox does not exist',
        'no such user',
        'recipient address rejected',
        'recipient not found',
        'unknown user',
        'does not exist',
        'invalid recipient',
        '550 5.1.1',
        '550 5.1.10',
        '551 5.1.1',
        '553 5.1.2',
        'permanent failure',
        'undeliverable',
    ],
];
