<?php

declare(strict_types=1);

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\AccessTokenService;
use App\Services\Auth\RefreshTokenService;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

it('revokes the replacement pair and every other active session when a rotated predecessor is reused', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    Log::spy();

    $loginResponse = loginAdminForTests();
    $predecessorRefreshToken = (string) $loginResponse->json('data.refreshToken');

    $firstRefreshResponse = $this->postJson('/api/v1/admin/auth/refresh', [
        'refreshToken' => $predecessorRefreshToken,
    ], [
        'Accept-Language' => 'en',
    ]);

    $replacementAccessToken = (string) $firstRefreshResponse->json('data.accessToken');
    $replacementRefreshToken = (string) $firstRefreshResponse->json('data.refreshToken');

    $user = User::query()->sole();
    $accessTokenService = app(AccessTokenService::class);
    $refreshTokenService = app(RefreshTokenService::class);

    $extraAccessToken = $accessTokenService->issueFor($user)->plainTextToken;
    $extraRefreshToken = $refreshTokenService->issueFor($user)['plainTextToken'];

    $reuseResponse = $this->postJson('/api/v1/admin/auth/refresh', [
        'refreshToken' => $predecessorRefreshToken,
    ], [
        'Accept-Language' => 'en',
    ]);

    $reuseResponse->assertStatus(401)
        ->assertJsonPath('code', 'REFRESH_TOKEN_INVALID');

    expect(User::query()->sole()->tokens()->count())->toBe(0)
        ->and(RefreshToken::query()->whereNull('revoked_at')->count())->toBe(0);

    Log::shouldHaveReceived('warning')
        ->with('admin_auth.refresh_reuse_detected', Mockery::on(function (array $context) use ($predecessorRefreshToken, $replacementRefreshToken, $extraRefreshToken): bool {
            $serializedContext = json_encode($context, JSON_THROW_ON_ERROR);

            return ! str_contains($serializedContext, $predecessorRefreshToken)
                && ! str_contains($serializedContext, $replacementRefreshToken)
                && ! str_contains($serializedContext, $extraRefreshToken);
        }))
        ->once();
});
