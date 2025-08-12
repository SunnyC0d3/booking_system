<?php

return [
    'paths' => ['api/v1/*', 'oauth/token', 'oauth/authorize'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    'allowed_origins' => [
        env('APP_URL'),
        env('APP_URL_FRONTEND'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,
];
