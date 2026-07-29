<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'OPTIONS'],

    // AppServiceProvider sets this from the normalized single source of truth
    // at services.admin_frontend.origin.
    'allowed_origins' => [],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Accept-Language',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Requested-With',
    ],

    'exposed_headers' => [
        'Content-Language',
    ],

    'max_age' => 0,

    'supports_credentials' => false,

];
