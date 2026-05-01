<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | BLOXY core configuration
    |--------------------------------------------------------------------------
    |
    | Settings that govern bloxy-core behavior. Host applications can override
    | by publishing this config (`php artisan vendor:publish --tag=bloxy-config`)
    | and editing the resulting `config/bloxy.php`.
    |
    */

    'observability' => [
        /*
        |----------------------------------------------------------------------
        | Redaction allowlist
        |----------------------------------------------------------------------
        |
        | Field names matching these patterns are replaced with the
        | `redaction_marker` before being sent to logs, Sentry, or any other
        | observability sink. Matching is case-insensitive substring.
        |
        */
        'redaction' => [
            'allowlist' => [
                'password',
                'password_confirmation',
                'token',
                'api_key',
                'secret',
                'authorization',
                'cookie',
                'set-cookie',
            ],
            'marker' => '[REDACTED]',
        ],
    ],
];
