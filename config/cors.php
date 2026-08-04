<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'OPTIONS'],

    // AppServiceProvider sets this from the normalized allow-list
    // at services.admin_frontend.origins.
    'allowed_origins' => [],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Accept-Language',
        'Authorization',
        'Content-Type',
        'Idempotency-Key',
        'Origin',
        'X-Requested-With',
    ],

    'exposed_headers' => [
        'Content-Language',
    ],

    'max_age' => 0,

    'supports_credentials' => false,

];
