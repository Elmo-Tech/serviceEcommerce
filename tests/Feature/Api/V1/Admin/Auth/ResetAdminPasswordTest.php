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

it('resets the password with a valid reset token and revokes every session', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->sole();
    $workflow = PasswordReset::factory()->forUser($user)->create([
        'email_normalized' => $user->email,
        'code_hash' => Hash::make('654321'),
        'code_expires_at' => now()->addMinutes(10),
        'verified_at' => now(),
        'reset_token_hash' => hash('sha256', 'reset-token-value'),
        'reset_token_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v1/admin/auth/reset-password', [
        'email' => 'admin@example.test',
        'resetToken' => 'reset-token-value',
        'password' => 'NewAdminPassword1!',
        'passwordConfirmation' => 'NewAdminPassword1!',
    ], [
        'Accept-Language' => 'en',
    ]);

    $response->assertOk()
        ->assertJsonPath('message', trans('auth.password_reset_success', [], 'en'))
        ->assertJsonPath('data', []);

    $workflow->refresh();

    expect(Hash::check('NewAdminPassword1!', $user->fresh()->password))->toBeTrue()
        ->and($workflow->consumed_at)->not->toBeNull();

    loginAdminForTests(['password' => 'AdminPassword1!'])
        ->assertStatus(401);

    loginAdminForTests(['password' => 'NewAdminPassword1!'])
        ->assertOk();
});

it('rejects invalid reset tokens', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->sole();
    PasswordReset::factory()->forUser($user)->create([
        'email_normalized' => $user->email,
        'code_hash' => Hash::make('654321'),
        'code_expires_at' => now()->addMinutes(10),
        'verified_at' => now(),
        'reset_token_hash' => hash('sha256', 'reset-token-value'),
        'reset_token_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v1/admin/auth/reset-password', [
        'email' => 'admin@example.test',
        'resetToken' => 'wrong-token',
        'password' => 'NewAdminPassword1!',
        'passwordConfirmation' => 'NewAdminPassword1!',
    ], [
        'Accept-Language' => 'en',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'PASSWORD_RESET_TOKEN_INVALID');
});

it('enforces reset-password validation and does not transform reset token bytes', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $validationError = $this->postJson('/api/v1/admin/auth/reset-password', [
        'email' => 'admin@example.test',
        'resetToken' => '',
        'password' => 'short',
        'passwordConfirmation' => 'different',
    ], [
        'Accept-Language' => 'en',
    ]);

    $user = User::query()->sole();
    PasswordReset::factory()->forUser($user)->create([
        'email_normalized' => $user->email,
        'code_hash' => Hash::make('654321'),
        'code_expires_at' => now()->addMinutes(10),
        'verified_at' => now(),
        'reset_token_hash' => hash('sha256', 'reset-token-value'),
        'reset_token_expires_at' => now()->addMinutes(10),
    ]);

    $transformedToken = $this->postJson('/api/v1/admin/auth/reset-password', [
        'email' => 'admin@example.test',
        'resetToken' => 'reset-token-value ',
        'password' => 'NewAdminPassword1!',
        'passwordConfirmation' => 'NewAdminPassword1!',
    ], [
        'Accept-Language' => 'en',
    ]);

    $validationError->assertStatus(HttpStatusCode::UNPROCESSABLE_ENTITY->value)
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    $transformedToken->assertStatus(HttpStatusCode::UNPROCESSABLE_ENTITY->value)
        ->assertJsonPath('code', 'PASSWORD_RESET_TOKEN_INVALID');
});

it('rejects expired and consumed reset-token workflows with the stable invalid contract', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->sole();

    PasswordReset::factory()->forUser($user)->create([
        'email_normalized' => $user->email,
        'verified_at' => now(),
        'reset_token_hash' => hash('sha256', 'expired-token'),
        'reset_token_expires_at' => now()->subMinute(),
    ]);

    $expired = $this->postJson('/api/v1/admin/auth/reset-password', [
        'email' => 'admin@example.test',
        'resetToken' => 'expired-token',
        'password' => 'ExpiredPassword1!',
        'passwordConfirmation' => 'ExpiredPassword1!',
    ], [
        'Accept-Language' => 'en',
    ]);

    PasswordReset::query()->delete();

    PasswordReset::factory()->forUser($user)->create([
        'email_normalized' => $user->email,
        'verified_at' => now(),
        'reset_token_hash' => hash('sha256', 'consumed-token'),
        'reset_token_expires_at' => now()->addMinutes(10),
        'consumed_at' => now(),
    ]);

    $consumed = $this->postJson('/api/v1/admin/auth/reset-password', [
        'email' => 'admin@example.test',
        'resetToken' => 'consumed-token',
        'password' => 'ConsumedPassword1!',
        'passwordConfirmation' => 'ConsumedPassword1!',
    ], [
        'Accept-Language' => 'en',
    ]);

    $expired->assertStatus(HttpStatusCode::UNPROCESSABLE_ENTITY->value)
        ->assertJsonPath('code', 'PASSWORD_RESET_TOKEN_INVALID');

    $consumed->assertStatus(HttpStatusCode::UNPROCESSABLE_ENTITY->value)
        ->assertJsonPath('code', 'PASSWORD_RESET_TOKEN_INVALID');
});

it('returns no replacement token or auth cookie after a successful password reset', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->sole();
    $loginResponse = loginAdminForTests();
    $oldAccessToken = (string) $loginResponse->json('data.accessToken');
    $oldRefreshToken = (string) $loginResponse->json('data.refreshToken');

    PasswordReset::factory()->forUser($user)->create([
        'email_normalized' => $user->email,
        'code_hash' => Hash::make('654321'),
        'code_expires_at' => now()->addMinutes(10),
        'verified_at' => now(),
        'reset_token_hash' => hash('sha256', 'reset-token-value'),
        'reset_token_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v1/admin/auth/reset-password', [
        'email' => 'admin@example.test',
        'resetToken' => 'reset-token-value',
        'password' => 'AnotherAdminPassword1!',
        'passwordConfirmation' => 'AnotherAdminPassword1!',
    ], [
        'Accept-Language' => 'en',
    ]);

    $response->assertOk()
        ->assertJsonPath('data', [])
        ->assertHeader('Content-Language', 'en')
        ->assertHeaderMissing('Set-Cookie');

    expect($response->json('data'))->not->toHaveKey('accessToken')
        ->and($response->json('data'))->not->toHaveKey('refreshToken')
        ->and($response->headers->getCookies())->toBe([])
        ->and($oldAccessToken)->toContain('|')
        ->and($oldRefreshToken)->not->toBe('');
});
