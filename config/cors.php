<?php

return [
    'paths' => ['api/*', 'oauth/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [env('APP_URL'), env('APP_URL_FRONTEND')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Authorization', 'X-Custom-Header'],

    'max_age' => 86400,

    'supports_credentials' => true,
];
