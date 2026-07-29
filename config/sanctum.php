<?php

return [

    'routes' => false,

    'stateful' => [],

    'guard' => [],

    'expiration' => (int) env('SANCTUM_EXPIRATION', 15),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [],

];
