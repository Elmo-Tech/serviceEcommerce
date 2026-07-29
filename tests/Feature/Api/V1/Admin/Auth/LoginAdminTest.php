<?php

declare(strict_types=1);

use App\Models\RefreshToken;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

beforeEach(function () {
    setLoginSeederEnvironment([
        'SUPER_ADMIN_NAME' => 'Service Commerce Super Admin',
        'SUPER_ADMIN_EMAIL' => 'admin@example.test',
        'SUPER_ADMIN_PASSWORD' => 'AdminPassword1!',
    ]);

    Route::middleware(['api', 'admin.auth.headers'])
        ->prefix('api/v1/admin/auth/_login-test')
        ->group(function (): void {
            Route::get('/protected', fn () => ApiResponse::success('ok', null))
                ->middleware(['auth:sanctum', 'admin.user_type', 'admin.active']);
        });
});

it('logs in successfully with normalized email and returns the approved token and profile contract', function () {
    $this->seed(SuperAdminSeeder::class);

    $response = $this->postJson('/api/v1/admin/auth/login', [
        'email' => '  ADMIN@EXAMPLE.TEST  ',
        'password' => 'AdminPassword1!',
    ], [
        'Accept-Language' => 'en',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', trans('auth.login_success', [], 'en'))
        ->assertJsonPath('data.tokenType', 'Bearer')
        ->assertJsonPath('data.tokenExpiresIn', 900)
        ->assertJsonPath('data.refreshTokenExpiresIn', 2592000)
        ->assertJsonPath('data.profile.name', 'Service Commerce Super Admin')
        ->assertJsonPath('data.profile.email', 'admin@example.test')
        ->assertJsonPath('data.profile.avatar', null)
        ->assertJsonPath('data.profile.role', 'super-admin')
        ->assertJsonPath('data.profile.permissions', [])
        ->assertJsonPath('data.refreshTokenExpiresIn', 2592000)
        ->assertJsonMissingPath('data.profile.id')
        ->assertJsonMissingPath('data.profile.roles')
        ->assertJsonMissingPath('data.profile.avatarUrl')
        ->assertHeader('Content-Language', 'en');

    $accessToken = (string) $response->json('data.accessToken');
    $refreshToken = (string) $response->json('data.refreshToken');
    expect($accessToken)->toContain('|');

    expect($refreshToken)->not->toBe('')
        ->and($response->headers->getCookies())->toBe([])
        ->and(User::query()->sole()->tokens()->count())->toBe(1)
        ->and(RefreshToken::query()->whereNull('revoked_at')->count())->toBe(1);
});

it('does not trim or transform the password before credential verification', function () {
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->sole();
    $user->forceFill([
        'password' => Hash::make('SpacePassword1! '),
    ])->save();

    $exactPasswordResponse = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin@example.test',
        'password' => 'SpacePassword1! ',
    ]);

    $exactPasswordResponse->assertOk();

    $trimmedPasswordResponse = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin@example.test',
        'password' => 'SpacePassword1!',
    ]);

    $trimmedPasswordResponse->assertStatus(401)
        ->assertJsonPath('code', 'INVALID_CREDENTIALS');
});

it('returns the same generic invalid-credentials contract for unknown email and wrong password', function () {
    $this->seed(SuperAdminSeeder::class);

    $unknownEmail = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'unknown@example.test',
        'password' => 'AdminPassword1!',
    ], [
        'Accept-Language' => 'en',
    ]);

    $wrongPassword = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin@example.test',
        'password' => 'WrongPassword1!',
    ], [
        'Accept-Language' => 'en',
    ]);

    $unknownEmail->assertStatus(401)
        ->assertJsonPath('message', trans('auth.invalid_credentials', [], 'en'))
        ->assertJsonPath('code', 'INVALID_CREDENTIALS');

    $wrongPassword->assertStatus(401)
        ->assertJsonPath('message', trans('auth.invalid_credentials', [], 'en'))
        ->assertJsonPath('code', 'INVALID_CREDENTIALS');
});

it('returns the same generic invalid-credentials contract for a non administrator type', function () {
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->sole();
    DB::table('users')
        ->where('id', $user->getKey())
        ->update(['type' => 1]);

    $response = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin@example.test',
        'password' => 'AdminPassword1!',
    ], [
        'Accept-Language' => 'en',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('message', trans('auth.invalid_credentials', [], 'en'))
        ->assertJsonPath('code', 'INVALID_CREDENTIALS');
});

it('rejects inactive administrators with the approved inactive-account response', function () {
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->sole();
    $user->forceFill(['is_active' => false])->save();

    $response = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin@example.test',
        'password' => 'AdminPassword1!',
    ], [
        'Accept-Language' => 'en',
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('message', trans('auth.user_inactive', [], 'en'))
        ->assertJsonPath('code', 'USER_INACTIVE');
});

it('rate limits login attempts by normalized email and requester ip', function () {
    $this->seed(SuperAdminSeeder::class);

    foreach (range(1, 5) as $attempt) {
        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => $attempt <= 3 ? '  ADMIN@EXAMPLE.TEST  ' : 'admin@example.test',
            'password' => 'WrongPassword1!',
        ], [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(401);
    }

    $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin@example.test',
        'password' => 'WrongPassword1!',
    ], [
        'Accept-Language' => 'en',
    ])->assertStatus(429)
        ->assertJsonPath('code', 'RATE_LIMITED');
});

it('replaces the previous session so only the newest access and refresh credentials remain active', function () {
    $this->seed(SuperAdminSeeder::class);

    $firstLogin = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin@example.test',
        'password' => 'AdminPassword1!',
    ]);

    $firstAccessToken = (string) $firstLogin->json('data.accessToken');
    $firstRefreshToken = (string) $firstLogin->json('data.refreshToken');

    $this->withHeader('Authorization', 'Bearer '.$firstAccessToken)
        ->getJson('/api/v1/admin/auth/_login-test/protected')
        ->assertOk();

    $secondLogin = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin@example.test',
        'password' => 'AdminPassword1!',
    ]);

    $secondAccessToken = (string) $secondLogin->json('data.accessToken');
    $secondRefreshToken = (string) $secondLogin->json('data.refreshToken');

    expect($secondAccessToken)->not->toBe($firstAccessToken)
        ->and($secondRefreshToken)->not->toBe('')
        ->and($firstRefreshToken)->not->toBe('')
        ->and($secondRefreshToken)->not->toBe($firstRefreshToken)
        ->and(User::query()->sole()->tokens()->count())->toBe(1)
        ->and(RefreshToken::query()->whereNull('revoked_at')->count())->toBe(1)
        ->and(PersonalAccessToken::findToken($firstAccessToken))->toBeNull()
        ->and(PersonalAccessToken::findToken($secondAccessToken))->not->toBeNull();

    app('auth')->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$firstAccessToken)
        ->getJson('/api/v1/admin/auth/_login-test/protected')
        ->assertStatus(401);

    app('auth')->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$secondAccessToken)
        ->getJson('/api/v1/admin/auth/_login-test/protected')
        ->assertOk();
});

function setLoginSeederEnvironment(array $values): void
{
    $values['SUPER_ADMIN_ADDITIONAL_USERS'] = '';

    foreach ($values as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
