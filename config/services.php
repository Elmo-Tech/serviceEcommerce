<?php

$rawAdminFrontendOrigins = (string) env('ADMIN_FRONTEND_ORIGINS', env('ADMIN_FRONTEND_ORIGIN', 'https://admin.example-frontend.com'));
$adminFrontendOrigins = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', $rawAdminFrontendOrigins) ?: [])));

$normalizeAdminFrontendOrigin = static function (string $origin): ?string {
    $originParts = parse_url($origin);

    if (
        ! is_array($originParts)
        || ! isset($originParts['scheme'], $originParts['host'])
        || isset($originParts['user'], $originParts['pass'], $originParts['query'], $originParts['fragment'])
        || (isset($originParts['path']) && ! in_array($originParts['path'], ['', '/'], true))
    ) {
        return null;
    }

    return strtolower($originParts['scheme'])
        .'://'.strtolower($originParts['host'])
        .(isset($originParts['port']) ? ':'.$originParts['port'] : '');
};

$adminFrontendOrigins = array_values(array_filter(array_map(
    $normalizeAdminFrontendOrigin,
    $adminFrontendOrigins,
)));

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

    'admin_frontend' => [
        'origin' => $adminFrontendOrigins[0] ?? null,
        'origins' => $adminFrontendOrigins,
        'origin_configured' => $adminFrontendOrigins !== [],
    ],

];
