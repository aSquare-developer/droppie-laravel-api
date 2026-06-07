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
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
        'routes_api_key' => env('GOOGLE_ROUTES_API_KEY', env('GOOGLE_MAPS_API_KEY')),
        'places_api_key' => env('GOOGLE_PLACES_API_KEY', env('GOOGLE_MAPS_API_KEY')),
        'address_validation_api_key' => env('GOOGLE_ADDRESS_VALIDATION_API_KEY', env('GOOGLE_MAPS_API_KEY')),
        'address_validation_enabled' => env('GOOGLE_ADDRESS_VALIDATION_ENABLED', false),
        'maps_language' => env('GOOGLE_MAPS_LANGUAGE', 'en'),
        'places_country' => env('GOOGLE_PLACES_COUNTRY'),
    ],

];
