<?php

declare(strict_types=1);

use App\Models\PasswordReset;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('maps every stable auth error to the approved http status and never returns obsolete errors', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->sole();

    $loginResponse = loginAdminForTests();
    $currentPasswordInvalidAccessToken = (string) $loginResponse->json('data.accessToken');

    $currentPasswordInvalid = $this->withHeader('Authorization', 'Bearer '.$currentPasswordInvalidAccessToken)
        ->putJson('/api/v1/admin/auth/change-password', [
            'currentPassword' => 'WrongPassword1!',
            'password' => 'NewAdminPassword1!',
            'passwordConfirmation' => 'NewAdminPassword1!',
        ], [
            'Accept-Language' => 'en',
        ]);

    $validationError = $this->postJson('/api/v1/admin/auth/login', [], [
        'Accept-Language' => 'en',
    ]);

    foreach (range(1, 5) as $_) {
        $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@example.test',
            'password' => 'WrongPassword1!',
        ], [
            'Accept-Language' => 'en',
        ]);
    }

    $rateLimited = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin@example.test',
        'password' => 'WrongPassword1!',
    ], [
        'Accept-Language' => 'en',
    ]);

    $refreshTokenInvalid = $this->postJson('/api/v1/admin/auth/refresh', [
        'refreshToken' => 'structurally-valid-but-unknown-token',
    ], [
        'Accept-Language' => 'en',
    ]);

    PasswordReset::factory()->forUser($user)->create([
        'email_normalized' => $user->email,
        'code_hash' => Hash::make('654321'),
        'code_expires_at' => now()->addMinutes(10),
    ]);

    $passwordResetCodeInvalid = $this->postJson('/api/v1/admin/auth/verify-forgot-password-code', [
        'email' => 'admin@example.test',
        'code' => '000000',
    ], [
        'Accept-Language' => 'en',
    ]);

    PasswordReset::factory()->forUser($user)->create([
        'email_normalized' => $user->email,
        'code_hash' => Hash::make('654321'),
        'code_expires_at' => now()->addMinutes(10),
        'verified_at' => now(),
        'reset_token_hash' => hash('sha256', 'reset-token-value'),
        'reset_token_expires_at' => now()->addMinutes(10),
    ]);

    $passwordResetTokenInvalid = $this->postJson('/api/v1/admin/auth/reset-password', [
        'email' => 'admin@example.test',
        'resetToken' => 'wrong-token',
        'password' => 'NewAdminPassword1!',
        'passwordConfirmation' => 'NewAdminPassword1!',
    ], [
        'Accept-Language' => 'en',
    ]);

    Mail::shouldReceive('to')->once()->andReturnSelf();
    Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('smtp-down'));

    $mailFailureAdmin = User::factory()->create([
        'name' => 'Mail Failure Admin',
        'email' => 'mailfail@example.test',
        'password' => 'MailFailPassword1!',
        'type' => 0,
        'is_active' => true,
    ]);
    $mailFailureAdmin->syncRoles(['super-admin']);

    $mailServiceUnavailable = $this->postJson('/api/v1/admin/auth/forgot-password', [
        'email' => 'mailfail@example.test',
    ], [
        'Accept-Language' => 'en',
    ]);

    $validationError->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    $rateLimited->assertStatus(429)->assertJsonPath('code', 'RATE_LIMITED');
    $currentPasswordInvalid->assertStatus(422)->assertJsonPath('code', 'CURRENT_PASSWORD_INVALID');
    $passwordResetCodeInvalid->assertStatus(422)->assertJsonPath('code', 'PASSWORD_RESET_CODE_INVALID');
    $passwordResetTokenInvalid->assertStatus(422)->assertJsonPath('code', 'PASSWORD_RESET_TOKEN_INVALID');
    $mailServiceUnavailable->assertStatus(503)->assertJsonPath('code', 'MAIL_SERVICE_UNAVAILABLE');

    foreach ([
        $validationError,
        $rateLimited,
        $currentPasswordInvalid,
        $passwordResetCodeInvalid,
        $passwordResetTokenInvalid,
        $mailServiceUnavailable,
    ] as $response) {
        expect($response->getContent())->not->toContain('ORIGIN_NOT_ALLOWED')
            ->not->toContain('CSRF_TOKEN_MISMATCH');
    }
});

it('maps user inactive through the approved login boundary', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->sole();
    $user->forceFill(['is_active' => false])->save();

    $response = loginAdminForTests();

    $response->assertStatus(403)
        ->assertJsonPath('code', 'USER_INACTIVE');
});
