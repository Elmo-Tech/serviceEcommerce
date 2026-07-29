<?php

declare(strict_types=1);

use App\Enums\HttpStatusCode;
use App\Models\PasswordReset;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

it('exchanges a valid code for a one-time reset token', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->sole();
    $workflow = PasswordReset::factory()->forUser($user)->create([
        'email_normalized' => $user->email,
        'code_hash' => Hash::make('654321'),
        'code_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v1/admin/auth/verify-forgot-password-code', [
        'email' => 'admin@example.test',
        'code' => '654321',
    ], [
        'Accept-Language' => 'en',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.resetTokenExpiresIn', 600)
        ->assertJsonStructure(['data' => ['resetToken', 'resetTokenExpiresIn']]);

    $workflow->refresh();

    expect($workflow->verified_at)->not->toBeNull()
        ->and($workflow->reset_token_hash)->not->toBeNull();
});

it('increments failed attempts and returns the generic invalid code contract', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->sole();
    PasswordReset::factory()->forUser($user)->create([
        'email_normalized' => $user->email,
        'code_hash' => Hash::make('654321'),
        'code_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v1/admin/auth/verify-forgot-password-code', [
        'email' => 'admin@example.test',
        'code' => '000000',
    ], [
        'Accept-Language' => 'en',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'PASSWORD_RESET_CODE_INVALID');

    expect(PasswordReset::query()->sole()->verification_attempts)->toBe(1);
});

it('does not transform the recovery code and rejects structurally invalid payloads', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->sole();
    PasswordReset::factory()->forUser($user)->create([
        'email_normalized' => $user->email,
        'code_hash' => Hash::make('654321'),
        'code_expires_at' => now()->addMinutes(10),
    ]);

    $trimmedCode = $this->postJson('/api/v1/admin/auth/verify-forgot-password-code', [
        'email' => 'admin@example.test',
        'code' => '654321 ',
    ], [
        'Accept-Language' => 'en',
    ]);

    $wrongShape = $this->postJson('/api/v1/admin/auth/verify-forgot-password-code', [
        'email' => 'admin@example.test',
    ], [
        'Accept-Language' => 'en',
    ]);

    $trimmedCode->assertStatus(HttpStatusCode::UNPROCESSABLE_ENTITY->value)
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    $wrongShape->assertStatus(HttpStatusCode::UNPROCESSABLE_ENTITY->value)
        ->assertJsonPath('code', 'VALIDATION_ERROR');
});

it('rejects expired consumed verified and attempt-limited workflows with the stable invalid contract', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->sole();

    $expired = PasswordReset::factory()->forUser($user)->expiredCode()->create([
        'code_hash' => Hash::make('654321'),
    ]);

    $consumed = PasswordReset::factory()->forUser($user)->consumed()->create([
        'code_hash' => Hash::make('654321'),
    ]);

    $verified = PasswordReset::factory()->forUser($user)->verified()->create([
        'code_hash' => Hash::make('654321'),
        'verified_at' => now(),
    ]);

    $attemptLimited = PasswordReset::factory()->forUser($user)->create([
        'code_hash' => Hash::make('654321'),
        'verification_attempts' => 5,
        'consumed_at' => now(),
    ]);

    foreach ([$expired, $consumed, $verified, $attemptLimited] as $workflow) {
        $response = $this->postJson('/api/v1/admin/auth/verify-forgot-password-code', [
            'email' => 'admin@example.test',
            'code' => '654321',
        ], [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(HttpStatusCode::UNPROCESSABLE_ENTITY->value)
            ->assertJsonPath('code', 'PASSWORD_RESET_CODE_INVALID');

        $workflow->refresh()->delete();
    }
});

it('consumes the workflow on the fifth failed attempt', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->sole();
    $workflow = PasswordReset::factory()->forUser($user)->create([
        'email_normalized' => $user->email,
        'code_hash' => Hash::make('654321'),
        'code_expires_at' => now()->addMinutes(10),
        'verification_attempts' => 4,
    ]);

    $response = $this->postJson('/api/v1/admin/auth/verify-forgot-password-code', [
        'email' => 'admin@example.test',
        'code' => '000000',
    ], [
        'Accept-Language' => 'en',
    ]);

    $response->assertStatus(HttpStatusCode::UNPROCESSABLE_ENTITY->value)
        ->assertJsonPath('code', 'PASSWORD_RESET_CODE_INVALID');

    expect($workflow->fresh()->verification_attempts)->toBe(5)
        ->and($workflow->fresh()->consumed_at)->not->toBeNull();
});

it('issues a secure reset token only once on successful verification', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->sole();
    $freshWorkflow = PasswordReset::factory()->forUser($user)->create([
        'email_normalized' => $user->email,
        'code_hash' => Hash::make('123456'),
        'code_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v1/admin/auth/verify-forgot-password-code', [
        'email' => 'admin@example.test',
        'code' => '123456',
    ], [
        'Accept-Language' => 'en',
    ]);

    $resetToken = (string) $response->json('data.resetToken');
    $base64 = strtr($resetToken, '-_', '+/');
    $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
    $decoded = base64_decode($base64, true);

    $response->assertOk()
        ->assertJsonPath('data.resetTokenExpiresIn', 600);

    expect($resetToken)->toMatch('/^[A-Za-z0-9_-]+$/')
        ->and($decoded)->toBeString()
        ->and(strlen((string) $decoded))->toBeGreaterThanOrEqual(32)
        ->and($freshWorkflow->fresh()->verified_at)->not->toBeNull()
        ->and($freshWorkflow->fresh()->reset_token_hash)->toBe(hash('sha256', $resetToken));
});
