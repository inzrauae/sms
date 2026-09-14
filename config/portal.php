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
    // Flat pay-as-you-go price, in rupees per SMS segment. Same rate for
    // every customer regardless of volume — no plans, no bulk tiers.
    'default_rate' => env('DEFAULT_RATE', '0.99'),
    'signup_bonus' => env('SIGNUP_BONUS', '10'),
    'support_email' => env('SUPPORT_EMAIL', 'support@example.lk'),
    'currency' => 'LKR',

    // Flat one-time charge (in rupees) to request a sender ID. Reserved when
    // the customer submits the request, refunded if an admin rejects it.
    'sender_id_fee' => env('SENDER_ID_FEE', '1000'),

    // PayPal cannot settle in LKR, so a top-up is charged in USD at this
    // rupees-per-dollar rate. Keep it close to the real exchange rate —
    // editable in the admin console, not a live forex feed.
    'paypal_usd_rate' => env('PAYPAL_USD_RATE', '300'),

    // Requests per minute per API token.
    'api_rate_limit' => (int) env('API_RATE_LIMIT', 120),

    // How often the scheduler polls Text.lk for delivery status, in seconds.
    'sync_interval' => (int) env('SYNC_INTERVAL_MS', 60000) / 1000,
];
