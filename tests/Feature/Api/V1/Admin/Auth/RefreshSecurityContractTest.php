<?php

declare(strict_types=1);

use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('refreshes without browser origin csrf or cookies and never returns obsolete auth errors', function () {
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
        ->assertJsonMissingPath('code')
        ->assertHeaderMissing('Set-Cookie');

    $invalid = $this->postJson('/api/v1/admin/auth/refresh', [
        'refreshToken' => 'structurally-valid-but-unknown-token',
    ], [
        'Accept-Language' => 'en',
    ]);

    $invalid->assertStatus(401)
        ->assertJsonPath('code', 'REFRESH_TOKEN_INVALID')
        ->assertJsonMissing(['code' => 'ORIGIN_NOT_ALLOWED'])
        ->assertJsonMissing(['code' => 'CSRF_TOKEN_MISMATCH']);
});
