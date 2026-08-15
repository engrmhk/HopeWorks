<?php

return [
    'jwt_secret' => env('LICENSE_JWT_SECRET', env('APP_KEY')),
    'impersonation_jwt_secret' => env('IMPERSONATION_JWT_SECRET', env('APP_KEY')),
    'internal_service_secret' => env('INTERNAL_SERVICE_SECRET', env('APP_KEY')),
    'impersonation_token_ttl_minutes' => (int) env('IMPERSONATION_TOKEN_TTL_MINUTES', 5),
    'impersonation_session_max_minutes' => (int) env('IMPERSONATION_SESSION_MAX_MINUTES', 30),

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_CURRENCY', 'USD'),
        'dunning_max_attempts' => (int) env('STRIPE_DUNNING_MAX_ATTEMPTS', 3),
        'checkout_success_url' => env('STRIPE_CHECKOUT_SUCCESS_URL', env('APP_URL').'/admin/subscriptions?checkout=success'),
        'checkout_cancel_url' => env('STRIPE_CHECKOUT_CANCEL_URL', env('APP_URL').'/admin/subscriptions?checkout=cancelled'),
        'portal_return_url' => env('STRIPE_PORTAL_RETURN_URL', env('APP_URL').'/admin'),
    ],

    'billing' => [
        // LBP is not supported by Stripe; use a regional processor if LBP billing is required.
        'currency' => env('BILLING_CURRENCY', 'USD'),
        'lbp_requires_regional_processor' => true,
    ],

    'tenant_health' => [
        'at_risk_no_login_days' => (int) env('TENANT_AT_RISK_NO_LOGIN_DAYS', 30),
    ],

    'offboarding' => [
        'retention_days' => (int) env('OFFBOARDING_RETENTION_DAYS', 90),
    ],

    /*
    | Temporarily gated until tenant import FK remapping (HopeWorks-church T1/P7-2)
    | and Control Plane dry-run orchestration (Part 1) are verified end-to-end.
    | Do not set AFFILIATION_CHANGE_ENABLED=true until that verification is done.
    */
    'tenant_migration' => [
        'affiliation_change_enabled' => (bool) env('AFFILIATION_CHANGE_ENABLED', false),
        'dry_run_cache_ttl_seconds' => (int) env('TENANT_MIGRATION_DRY_RUN_TTL', 3600),
    ],

    'messaging' => [
        'driver' => env('MESSAGING_DRIVER', 'log'),
        'staff_notify_to' => env('SUPPORT_STAFF_NOTIFY_TO'),
    ],

    /*
    | Subscription status evaluation (active → grace → suspended).
    | The artisan command subscriptions:evaluate-statuses is scheduled every minute
    | in routes/console.php. See docs/SUBSCRIPTION_STATUS_EVALUATION.md.
    */
    'subscription_enforcement' => [
        'schedule_expression' => '* * * * *', // every minute via Laravel Schedule::everyMinute()
        'schedule_human' => 'every minute',
        'command' => 'subscriptions:evaluate-statuses',
    ],

    'schema_version' => env('HOPEWORKS_SCHEMA_VERSION', '1.0.0'),
];
