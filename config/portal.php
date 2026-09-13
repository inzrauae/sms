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

    // Flat one-time charge (in rupees) to request a sender ID. Reserved when
    // the customer submits the request, refunded if an admin rejects it.
    'sender_id_fee' => env('SENDER_ID_FEE', '1000'),

    // Bulk top-up tiers offered on the billing page. Rate is rupees/SMS —
    // cheaper per message at higher volumes.
    'sms_packages' => [
        ['units' => 1000, 'rate' => 1.00],
        ['units' => 5000, 'rate' => 0.95],
        ['units' => 10000, 'rate' => 0.90],
        ['units' => 50000, 'rate' => 0.85],
        ['units' => 100000, 'rate' => 0.80],
    ],

    // Requests per minute per API token.
    'api_rate_limit' => (int) env('API_RATE_LIMIT', 120),

    // How often the scheduler polls Text.lk for delivery status, in seconds.
    'sync_interval' => (int) env('SYNC_INTERVAL_MS', 60000) / 1000,
];
