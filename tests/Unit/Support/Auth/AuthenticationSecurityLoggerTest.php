<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Auth\AuthenticationSecurityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

it('redacts sensitive secrets and hashes identity values for info logs', function () {
    Log::spy();

    $user = User::factory()->create();
    $logger = app(AuthenticationSecurityLogger::class);

    $logger->info('admin_auth.event', [
        'email' => ' Admin@Example.test ',
        'password' => 'SecretPassword1!',
        'refreshToken' => 'refresh-secret',
        'resetTokenHash' => hash('sha256', 'reset-secret'),
        'authorization' => 'Bearer secret-token',
        'sql' => 'select * from users',
        'stackTrace' => 'trace line',
        'credentials' => ['password' => 'SecretPassword1!'],
        'avatarPath' => 'avatars/admin/current.jpg',
        'ip' => '127.0.0.1',
        'user' => $user,
    ]);

    Log::shouldHaveReceived('info')
        ->once()
        ->with('admin_auth.event', Mockery::on(function (array $context) use ($user): bool {
            expect($context)->toBe([
                'email_hash' => hash('sha256', 'admin@example.test'),
                'ip' => '127.0.0.1',
                'user_id' => $user->getKey(),
            ]);

            return true;
        }));
});

it('never logs raw exception messages, sql, stack traces, or bearer metadata in error logs', function () {
    Log::spy();

    $user = User::factory()->create();
    $logger = app(AuthenticationSecurityLogger::class);

    $logger->error('admin_auth.failure', [
        'exception' => new RuntimeException('leaked-secret-token-value'),
        'errorMessage' => 'password reset failed',
        'token' => 'plain-token',
        'tokenHash' => hash('sha256', 'plain-token'),
        'authorization_header' => 'Bearer plain-token',
        'cookie_value' => 'cookie-secret',
        'trace' => 'stack trace',
        'sqlQuery' => 'update users set password = ?',
        'user' => $user,
    ]);

    Log::shouldHaveReceived('error')
        ->once()
        ->with('admin_auth.failure', Mockery::on(function (array $context) use ($user): bool {
            expect($context)->toBe([
                'exception_class' => RuntimeException::class,
                'user_id' => $user->getKey(),
            ]);

            return true;
        }));
});
