<?php

$rawAdminFrontendOrigin = trim((string) env('ADMIN_FRONTEND_ORIGIN', 'https://admin.example-frontend.com'));
$adminFrontendOriginParts = parse_url($rawAdminFrontendOrigin);

$adminFrontendOrigin = $rawAdminFrontendOrigin;

if (
    is_array($adminFrontendOriginParts)
    && isset($adminFrontendOriginParts['scheme'], $adminFrontendOriginParts['host'])
    && ! isset($adminFrontendOriginParts['user'], $adminFrontendOriginParts['pass'])
    && ! isset($adminFrontendOriginParts['query'], $adminFrontendOriginParts['fragment'])
    && (! isset($adminFrontendOriginParts['path']) || in_array($adminFrontendOriginParts['path'], ['', '/'], true))
) {
    $adminFrontendOrigin = strtolower($adminFrontendOriginParts['scheme'])
        .'://'.strtolower($adminFrontendOriginParts['host'])
        .(isset($adminFrontendOriginParts['port']) ? ':'.$adminFrontendOriginParts['port'] : '');
}

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
        'origin' => $adminFrontendOrigin,
        'origin_configured' => env('ADMIN_FRONTEND_ORIGIN') !== null,
    ],

];
