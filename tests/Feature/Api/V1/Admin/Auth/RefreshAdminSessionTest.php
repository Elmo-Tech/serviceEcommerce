<?php

declare(strict_types=1);

use App\Enums\HttpStatusCode;
use App\Models\RefreshToken;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Route::middleware(['api', 'admin.auth.headers'])
        ->prefix('api/v1/admin/auth/_refresh-test')
        ->group(function (): void {
            Route::get('/protected', fn () => ApiResponse::success('ok', null))
                ->middleware(['auth:sanctum', 'admin.user_type', 'admin.active']);
        });
});

afterEach(function () {
    Carbon::setTestNow();
});

it('refreshes the session with a body refresh token and rotates the token pair', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $loginResponse = loginAdminForTests();
    $refreshToken = (string) $loginResponse->json('data.refreshToken');

    $response = $this->postJson('/api/v1/admin/auth/refresh', [
        'refreshToken' => $refreshToken,
    ], [
        'Accept-Language' => 'en',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', trans('auth.refresh_success', [], 'en'))
        ->assertJsonPath('data.tokenType', 'Bearer')
        ->assertJsonPath('data.tokenExpiresIn', null)
        ->assertJsonPath('data.refreshTokenExpiresIn', 2592000)
        ->assertJsonMissingPath('data.profile')
        ->assertHeader('Content-Language', 'en');

    expect($response->json('data.accessToken'))->not->toBe($loginResponse->json('data.accessToken'))
        ->and($response->json('data.refreshToken'))->not->toBe($refreshToken)
        ->and($response->headers->getCookies())->toBe([])
        ->and(RefreshToken::query()->whereNull('revoked_at')->count())->toBe(1)
        ->and(User::query()->sole()->tokens()->count())->toBe(1);

    app('auth')->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.(string) $loginResponse->json('data.accessToken'))
        ->getJson('/api/v1/admin/auth/_refresh-test/protected')
        ->assertStatus(HttpStatusCode::UNAUTHORIZED->value);
});

it('rejects missing, malformed, revoked, or reused refresh body tokens', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $loginResponse = loginAdminForTests();
    $refreshToken = (string) $loginResponse->json('data.refreshToken');

    $invalidResponse = $this->postJson('/api/v1/admin/auth/refresh', [], [
        'Accept-Language' => 'en',
    ]);

    $invalidResponse->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    $response = $this->postJson('/api/v1/admin/auth/refresh', [
        'refreshToken' => $refreshToken,
    ], [
        'Accept-Language' => 'en',
    ]);

    $reused = $this->postJson('/api/v1/admin/auth/refresh', [
        'refreshToken' => $refreshToken,
    ], [
        'Accept-Language' => 'en',
    ]);

    $response->assertOk();
    $reused->assertStatus(401)
        ->assertJsonPath('code', 'REFRESH_TOKEN_INVALID');

    expect(User::query()->sole()->tokens()->count())->toBe(0);
});
