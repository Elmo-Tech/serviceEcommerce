<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the authenticated administrator profile with the exact approved shape', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);
    $expectedPermissions = User::query()
        ->sole()
        ->getAllPermissions()
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    $loginResponse = loginAdminForTests();
    $accessToken = (string) $loginResponse->json('data.accessToken');

    $response = $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->getJson('/api/v1/admin/auth/profile', [
            'Accept-Language' => 'en',
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Service Commerce Super Admin')
        ->assertJsonPath('data.email', 'admin@example.test')
        ->assertJsonPath('data.avatar', null)
        ->assertJsonPath('data.role', 'super-admin')
        ->assertJsonPath('data.permissions', $expectedPermissions)
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.roles')
        ->assertJsonMissingPath('data.avatarUrl');
});
