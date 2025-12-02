<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure the rate limiting for email sending per domain.
    | This prevents sending too many emails to the same domain in a short time.
    |
    */

    'rate_limit' => [
        'emails_per_domain' => env('MAIL_RATE_LIMIT_COUNT', env('EMAIL_RATE_LIMIT_COUNT', 1)),
        'seconds' => env('EMAIL_RATE_LIMIT_SECONDS', 10),
    ],
];
