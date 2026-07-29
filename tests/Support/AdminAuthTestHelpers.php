<?php

declare(strict_types=1);

use Database\Seeders\SuperAdminSeeder;
use Illuminate\Testing\TestResponse;

function seedAdminAuthEnvironment(array $values = []): void
{
    $defaults = [
        'SUPER_ADMIN_NAME' => 'Service Commerce Super Admin',
        'SUPER_ADMIN_EMAIL' => 'admin@example.test',
        'SUPER_ADMIN_PASSWORD' => 'AdminPassword1!',
        'SUPER_ADMIN_ADDITIONAL_USERS' => '',
        'AUTH_ACCESS_TOKEN_TTL_MINUTES' => '15',
        'AUTH_REFRESH_TOKEN_TTL_MINUTES' => '43200',
        'AUTH_PASSWORD_RESET_CODE_TTL_MINUTES' => '10',
        'AUTH_PASSWORD_RESET_TOKEN_TTL_MINUTES' => '10',
        'AUTH_PASSWORD_RESET_MAX_ATTEMPTS' => '5',
        'AUTH_PASSWORD_RESET_RESEND_COOLDOWN_SECONDS' => '60',
    ];

    foreach (array_merge($defaults, $values) as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    config()->set('auth.access_token_ttl_minutes', 15);
    config()->set('auth.refresh_token_ttl_minutes', 43200);
    config()->set('auth.password_reset_code_ttl_minutes', 10);
    config()->set('auth.password_reset_token_ttl_minutes', 10);
    config()->set('auth.password_reset_max_attempts', 5);
    config()->set('auth.password_reset_resend_cooldown_seconds', 60);
}

function loginAdminForTests(array $overrides = [], array $headers = []): TestResponse
{
    $payload = array_merge([
        'email' => 'admin@example.test',
        'password' => 'AdminPassword1!',
    ], $overrides);

    return test()
        ->postJson('/api/v1/admin/auth/login', $payload, array_merge([
            'Accept-Language' => 'en',
        ], $headers));
}

function seedSuperAdminForAuthTests(array $values = []): void
{
    seedAdminAuthEnvironment($values);
    test()->seed(SuperAdminSeeder::class);
}
