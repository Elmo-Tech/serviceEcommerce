<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('changes the password, revokes tokens, and requires the new password afterward', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $loginResponse = loginAdminForTests();
    $accessToken = (string) $loginResponse->json('data.accessToken');

    $response = $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->putJson('/api/v1/admin/auth/change-password', [
            'currentPassword' => 'AdminPassword1!',
            'password' => 'NewAdminPassword1!',
            'passwordConfirmation' => 'NewAdminPassword1!',
        ], [
            'Accept-Language' => 'en',
        ]);

    $response->assertOk()
        ->assertJsonPath('message', trans('auth.password_changed', [], 'en'))
        ->assertJsonPath('data', []);

    $user = User::query()->sole();

    expect(Hash::check('NewAdminPassword1!', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);

    loginAdminForTests(['password' => 'AdminPassword1!'])
        ->assertStatus(401);

    loginAdminForTests(['password' => 'NewAdminPassword1!'])
        ->assertOk();
});

it('rejects invalid current passwords and password-policy violations', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $accessToken = (string) loginAdminForTests()->json('data.accessToken');

    $currentPasswordResponse = $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->putJson('/api/v1/admin/auth/change-password', [
            'currentPassword' => 'WrongPassword1!',
            'password' => 'NewAdminPassword1!',
            'passwordConfirmation' => 'NewAdminPassword1!',
        ], [
            'Accept-Language' => 'en',
        ]);

    $currentPasswordResponse->assertStatus(422)
        ->assertJsonPath('code', 'CURRENT_PASSWORD_INVALID');

    $policyResponse = $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->putJson('/api/v1/admin/auth/change-password', [
            'currentPassword' => 'AdminPassword1!',
            'password' => 'short',
            'passwordConfirmation' => 'short',
        ], [
            'Accept-Language' => 'en',
        ]);

    $policyResponse->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');
});
