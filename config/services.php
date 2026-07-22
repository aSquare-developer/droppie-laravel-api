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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
        'routes_api_key' => env('GOOGLE_ROUTES_API_KEY', env('GOOGLE_MAPS_API_KEY')),
        'routes_connect_timeout' => (float) env('GOOGLE_ROUTES_CONNECT_TIMEOUT', 3),
        'routes_timeout' => (float) env('GOOGLE_ROUTES_TIMEOUT', 10),
        'routes_max_attempts' => (int) env('GOOGLE_ROUTES_MAX_ATTEMPTS', 3),
        'routes_retry_delay_ms' => (int) env('GOOGLE_ROUTES_RETRY_DELAY_MS', 250),
        'places_api_key' => env('GOOGLE_PLACES_API_KEY', env('GOOGLE_MAPS_API_KEY')),
        'address_validation_api_key' => env('GOOGLE_ADDRESS_VALIDATION_API_KEY', env('GOOGLE_MAPS_API_KEY')),
        'address_validation_enabled' => env('GOOGLE_ADDRESS_VALIDATION_ENABLED', false),
        'maps_language' => env('GOOGLE_MAPS_LANGUAGE', 'en'),
        'places_country' => env('GOOGLE_PLACES_COUNTRY'),
    ],

];
