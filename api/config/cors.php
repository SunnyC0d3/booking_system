<?php

return [
    'paths' => ['web/*', 'api/*', 'oauth/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [env('APP_URL')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],

    'exposed_headers' => ['Authorization', 'X-Custom-Header', 'Content-Type'],

    'max_age' => 86400,

    'supports_credentials' => true,
];
