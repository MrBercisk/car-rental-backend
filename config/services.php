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
    'doku' => [
        'is_production' => env('DOKU_IS_PRODUCTION', false),
        'client_id' => env('DOKU_CLIENT_ID'),
        'secret_key' => env('DOKU_SECRET_KEY'),
        'base_url' => env('DOKU_IS_PRODUCTION', false)
            ? 'https://api.doku.com'
            : 'https://api-sandbox.doku.com',
    ],

    'payment_gateways' => [
        'default' => env('PAYMENT_GATEWAY_DEFAULT', 'doku'),
        'doku' => [
            'driver' => 'doku',
            'class' => App\Services\DokuCheckoutService::class,
        ],
    ],

    // ai key gemini
    'gemini' => ['api_key' => env('GEMINI_API_KEY')],

];
