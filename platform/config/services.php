<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'video' => ['provider' => env('VIDEO_PROVIDER', 'bunny')],

    'bunny' => [
        'library_id' => env('BUNNY_LIBRARY_ID'),
        'api_key' => env('BUNNY_API_KEY'),
        'cdn_host' => env('BUNNY_CDN_HOST'),
        'token_key' => env('BUNNY_TOKEN_KEY'),
    ],

    'payment' => [
        'provider' => env('PAYMENT_PROVIDER', 'manual'),
        'fake_secret' => env('PAYMENT_FAKE_SECRET', 'fake-webhook-secret'),
        'form_action_hosts' => array_filter(explode(',', (string) env('PAYMENT_FORM_HOSTS', 'https://epoint.az'))),
    ],

    'epoint' => [
        'public_key' => env('EPOINT_PUBLIC_KEY'),
        'private_key' => env('EPOINT_PRIVATE_KEY'),
        'api_url' => env('EPOINT_API_URL', 'https://epoint.az/api/1'),
    ],

    'clamav' => ['host' => env('CLAMAV_HOST', 'clamav'), 'port' => (int) env('CLAMAV_PORT', 3310)],
];
