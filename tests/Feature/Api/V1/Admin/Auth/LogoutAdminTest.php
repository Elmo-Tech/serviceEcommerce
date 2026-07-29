<?php

declare(strict_types=1);

use App\Models\RefreshToken;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('logs out the authenticated administrator and revokes all session credentials', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $loginResponse = loginAdminForTests();
    $accessToken = (string) $loginResponse->json('data.accessToken');

    $response = $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->postJson('/api/v1/admin/auth/logout', [], [
            'Accept-Language' => 'en',
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', trans('auth.logout_success', [], 'en'))
        ->assertJsonPath('data', []);

    expect($response->headers->getCookies())->toBe([])
        ->and(RefreshToken::query()->whereNull('revoked_at')->count())->toBe(0)
        ->and(User::query()->sole()->tokens()->count())->toBe(0);
});
