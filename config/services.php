<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'textlk' => [
        'token' => env('TEXTLK_API_TOKEN'),
        'base_url' => env('TEXTLK_BASE_URL', 'https://app.text.lk/api/v3'),
        'timeout' => (int) env('TEXTLK_TIMEOUT_MS', 20000),
    ],

    // PayPal Orders API v2. Client ID is safe to expose to the browser (the
    // JS SDK needs it to render the buttons) — only the secret is
    // confidential, and it never leaves this server.
    'paypal' => [
        'mode' => env('PAYPAL_MODE', 'sandbox'), // 'sandbox' or 'live'
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
    ],

];
