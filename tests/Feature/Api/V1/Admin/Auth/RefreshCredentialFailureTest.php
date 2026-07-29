<?php

declare(strict_types=1);

use App\Enums\HttpStatusCode;
use App\Enums\RefreshTokenRevocationReason;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

it('rejects unknown expired revoked and transformed refresh tokens with the stable invalid contract', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->sole();
    $service = app(RefreshTokenService::class);
    $now = now();

    $unknownResponse = $this->postJson('/api/v1/admin/auth/refresh', [
        'refreshToken' => 'structurally-valid-but-unknown-token',
    ], [
        'Accept-Language' => 'en',
    ]);

    $issued = $service->issueFor($user, null, $now);
    $expiredPlainTextToken = $issued['plainTextToken'];
    $issued['token']->forceFill([
        'expires_at' => $now->copy()->subMinute(),
    ])->save();

    $expiredResponse = $this->postJson('/api/v1/admin/auth/refresh', [
        'refreshToken' => $expiredPlainTextToken,
    ], [
        'Accept-Language' => 'en',
    ]);

    $revoked = $service->issueFor($user, null, $now);
    $revokedPlainTextToken = $revoked['plainTextToken'];
    $service->revoke($revoked['token'], RefreshTokenRevocationReason::LOGOUT, $now);

    $revokedResponse = $this->postJson('/api/v1/admin/auth/refresh', [
        'refreshToken' => $revokedPlainTextToken,
    ], [
        'Accept-Language' => 'en',
    ]);

    $transformedResponse = $this->postJson('/api/v1/admin/auth/refresh', [
        'refreshToken' => mb_strtolower((string) loginAdminForTests()->json('data.refreshToken')),
    ], [
        'Accept-Language' => 'en',
    ]);

    foreach ([$unknownResponse, $expiredResponse, $revokedResponse, $transformedResponse] as $response) {
        $response->assertStatus(HttpStatusCode::UNAUTHORIZED->value)
            ->assertJsonPath('code', 'REFRESH_TOKEN_INVALID');
    }
});

it('rejects ownerless deleted-owner inactive-owner invalid-family and invalid-rotation refresh tokens', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->sole();
    $service = app(RefreshTokenService::class);
    $now = now();

    $ownerlessPlainTextToken = $service->generatePlainTextToken();
    RefreshToken::factory()->create([
        'tokenable_type' => User::class,
        'tokenable_id' => 9_999_999,
        'token_hash' => $service->hashToken($ownerlessPlainTextToken),
        'family_id' => (string) Str::uuid(),
        'expires_at' => $now->copy()->addDays(30),
    ]);

    $deletedOwner = User::factory()->administrator()->create();
    $deletedOwner->assignRole('super-admin');
    $deletedOwnerIssued = $service->issueFor($deletedOwner, null, $now);
    $deletedOwnerPlainTextToken = $deletedOwnerIssued['plainTextToken'];
    $deletedOwner->delete();

    $inactiveOwner = User::factory()->administrator()->inactive()->create();
    $inactiveOwner->assignRole('super-admin');
    $inactiveOwnerIssued = $service->issueFor($inactiveOwner, null, $now);
    $inactiveOwnerPlainTextToken = $inactiveOwnerIssued['plainTextToken'];

    $invalidFamilyIssued = $service->issueFor($user, null, $now);
    $invalidFamilyPlainTextToken = $invalidFamilyIssued['plainTextToken'];
    $invalidFamilyIssued['token']->forceFill([
        'family_id' => '',
    ])->save();

    $invalidRotationIssued = $service->issueFor($user, null, $now);
    $invalidRotationPlainTextToken = $invalidRotationIssued['plainTextToken'];
    $rotationPlaceholder = $service->issueFor($user, $invalidRotationIssued['token']->family_id, $now);
    $invalidRotationIssued['token']->forceFill([
        'rotated_to_token_id' => $rotationPlaceholder['token']->getKey(),
    ])->save();

    $responses = [
        $this->postJson('/api/v1/admin/auth/refresh', [
            'refreshToken' => $ownerlessPlainTextToken,
        ], ['Accept-Language' => 'en']),
        $this->postJson('/api/v1/admin/auth/refresh', [
            'refreshToken' => $deletedOwnerPlainTextToken,
        ], ['Accept-Language' => 'en']),
        $this->postJson('/api/v1/admin/auth/refresh', [
            'refreshToken' => $inactiveOwnerPlainTextToken,
        ], ['Accept-Language' => 'en']),
        $this->postJson('/api/v1/admin/auth/refresh', [
            'refreshToken' => $invalidFamilyPlainTextToken,
        ], ['Accept-Language' => 'en']),
        $this->postJson('/api/v1/admin/auth/refresh', [
            'refreshToken' => $invalidRotationPlainTextToken,
        ], ['Accept-Language' => 'en']),
    ];

    foreach ($responses as $response) {
        $response->assertStatus(HttpStatusCode::UNAUTHORIZED->value)
            ->assertJsonPath('code', 'REFRESH_TOKEN_INVALID');
    }
});
