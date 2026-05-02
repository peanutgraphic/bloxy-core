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

            /*
            |------------------------------------------------------------------
            | Auto-wiring
            |------------------------------------------------------------------
            |
            | When auto_wire_monolog is true, BloxyCoreServiceProvider attaches
            | RedactingProcessor to the DEFAULT Monolog channel only — named
            | channels (Log::channel('slack'), etc.) are not covered; push the
            | processor manually in those channels' configs if needed.
            |
            | When auto_wire_sentry is true AND Sentry's PHP SDK is installed
            | (Sentry\State\Hub class exists), the provider sets a before_send
            | callback that recursively walks every Sentry event payload
            | through Redactor before transmission.
            |
            | Both default to true. Set to false in apps that need custom
            | observability wiring.
            |
            */
            'auto_wire_monolog' => env('BLOXY_REDACTOR_AUTO_WIRE_MONOLOG', true),
            'auto_wire_sentry' => env('BLOXY_REDACTOR_AUTO_WIRE_SENTRY', true),
        ],
    ],

    'audit' => [
        /*
        |----------------------------------------------------------------------
        | HTTP audit-middleware alias
        |----------------------------------------------------------------------
        |
        | The middleware is registered under this alias. Apps attach it to
        | route groups via `Route::middleware($alias)->group(...)`.
        |
        */
        'middleware_alias' => 'bloxy.audit',

        /*
        |----------------------------------------------------------------------
        | Tamper-evident chain (G6, opt-in)
        |----------------------------------------------------------------------
        |
        | When enabled, every audit_log row's `signature` is HMAC-SHA-256 of
        | (previous_row_signature || canonical_row_json) using the active
        | HMAC key. `bloxy:audit-verify-chain` walks recent rows and reports
        | tampering. Old keys must NEVER be deleted from `hmac_keys` — the
        | verifier uses each row's own `signing_key_id` to look up the key
        | that originally signed it.
        |
        | First-time activation requires `php artisan bloxy:audit-anchor
        | --reason="chain enablement"` so the verifier has a known anchor.
        |
        */
        'signed_chain' => env('BLOXY_AUDIT_SIGNED_CHAIN', false),
        'hmac_keys' => array_filter([
            1 => env('BLOXY_AUDIT_HMAC_KEY_1'),
            2 => env('BLOXY_AUDIT_HMAC_KEY_2'),
            3 => env('BLOXY_AUDIT_HMAC_KEY_3'),
        ]),
        'active_signing_key_id' => (int) env('BLOXY_AUDIT_ACTIVE_SIGNING_KEY_ID', 1),

        /*
        |----------------------------------------------------------------------
        | Request body capture (G7, opt-in)
        |----------------------------------------------------------------------
        |
        | null      — off (default; matches shipped behavior).
        | 'redacted'— middleware reads body up to request_body_max_bytes,
        |             runs through Redactor, stores under audit_log.meta.body.
        | 'full'    — same but no redaction. DO NOT enable in production.
        |
        | Multipart bodies (file uploads) are detected and stored as
        | {"_omitted": "multipart/form-data"} regardless of mode.
        |
        */
        'capture_request_body' => env('BLOXY_AUDIT_CAPTURE_REQUEST_BODY', null),
        'request_body_max_bytes' => (int) env('BLOXY_AUDIT_REQUEST_BODY_MAX_BYTES', 65536),

        /*
        |----------------------------------------------------------------------
        | Audit-coverage exclude patterns (G8)
        |----------------------------------------------------------------------
        |
        | URI patterns (PCRE regex) to skip in `bloxy:audit-coverage`. Default
        | excludes Laravel's framework-registered storage route (PUT
        | storage/{path}, registered by FilesystemServiceProvider when
        | `filesystems.disks.local.serve` is true — the framework default).
        | Add patterns for any other vendor-installed state-changing routes
        | (Sanctum, Telescope, Horizon) that you do not want flagged.
        |
        */
        'coverage_excludes' => [
            '#^storage/#',
        ],
    ],

    'rbac' => [
        /*
        |----------------------------------------------------------------------
        | Predicate evaluation (M1.4+)
        |----------------------------------------------------------------------
        |
        | M1.3 ships the storage slot for `activation_predicate` only. Any
        | non-null predicate is treated as "inactive" until M1.4+ plugs in a
        | predicate evaluator. This config key reserves the namespace.
        |
        */
        'predicate_evaluator' => null,
    ],
];
