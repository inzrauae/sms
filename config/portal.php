<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Portal defaults
    |--------------------------------------------------------------------------
    |
    | Seeded into the settings table on first boot. After that, the admin
    | console is the source of truth — see App\Services\Settings.
    |
    */

    'brand_name' => env('BRAND_NAME', 'Lankalink SMS'),
    'default_rate' => env('DEFAULT_RATE', '1.10'),
    'signup_bonus' => env('SIGNUP_BONUS', '10'),
    'support_email' => env('SUPPORT_EMAIL', 'support@example.lk'),
    'currency' => 'LKR',

    // Requests per minute per API token.
    'api_rate_limit' => (int) env('API_RATE_LIMIT', 120),

    // How often the scheduler polls Text.lk for delivery status, in seconds.
    'sync_interval' => (int) env('SYNC_INTERVAL_MS', 60000) / 1000,
];
