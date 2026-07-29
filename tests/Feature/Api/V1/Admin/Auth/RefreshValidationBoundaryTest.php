<?php

declare(strict_types=1);

use App\Enums\HttpStatusCode;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('accepts only a non-empty refreshToken field from the JSON body', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $cases = [
        [],
        ['refreshToken' => null],
        ['refreshToken' => ''],
        ['refreshToken' => []],
    ];

    foreach ($cases as $payload) {
        $this->postJson('/api/v1/admin/auth/refresh', $payload, [
            'Accept-Language' => 'en',
        ])->assertStatus(HttpStatusCode::UNPROCESSABLE_ENTITY->value)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }
});

it('rejects refresh tokens supplied through query string headers or cookies', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $requests = [
        $this->postJson('/api/v1/admin/auth/refresh?refreshToken=not-accepted', [], [
            'Accept-Language' => 'en',
        ]),
        $this->withHeader('X-Refresh-Token', 'not-accepted')
            ->postJson('/api/v1/admin/auth/refresh', [], [
                'Accept-Language' => 'en',
            ]),
        $this->withCookie('refreshToken', 'not-accepted')
            ->postJson('/api/v1/admin/auth/refresh', [], [
                'Accept-Language' => 'en',
            ]),
    ];

    foreach ($requests as $response) {
        $response->assertStatus(HttpStatusCode::UNPROCESSABLE_ENTITY->value)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }
});
