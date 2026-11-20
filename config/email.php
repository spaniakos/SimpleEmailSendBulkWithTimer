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
        'seconds' => env('EMAIL_RATE_LIMIT_SECONDS', 240),
        'max_emails_per_window' => env('MAIL_MAX_EMAILS_PER_WINDOW', 6),
        'window_seconds' => env('MAIL_RATE_LIMIT_WINDOW_SECONDS', 60),
        'max_emails_per_domain' => env('MAIL_MAX_EMAILS_PER_DOMAIN', env('MAIL_RATE_LIMIT_COUNT', env('EMAIL_RATE_LIMIT_COUNT', 1))),
        'domain_window_seconds' => env('MAIL_DOMAIN_RATE_LIMIT_SECONDS', env('EMAIL_RATE_LIMIT_SECONDS', 240)),
    ],
];
