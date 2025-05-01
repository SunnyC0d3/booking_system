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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'app_frontend_url' => env('APP_URL_FRONTEND'),
    'app_frontend_pwr' => env('APP_URL_FRONTEND_PASSWORD_RESET'),
    'app_frontend_email_verify' => env('APP_URL_FRONTEND_EMAIL_VERIFIED'),
    'hmac_secret' => env('HMAC_SECRET_KEY'),
    'passport_pa_id' => env('PASSPORT_PERSONAL_ACCESS_CLIENT_ID'),
    'passport_pa_secret' => env('PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET'),
];
