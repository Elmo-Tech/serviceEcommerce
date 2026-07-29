<?php

declare(strict_types=1);

use App\Models\PasswordReset;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('returns the approved success envelope, typed data, and required headers across auth success responses', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $login = loginAdminForTests();
    $loginData = $login->json('data');

    $profile = $this->withHeader('Authorization', 'Bearer '.$loginData['accessToken'])
        ->getJson('/api/v1/admin/auth/profile', ['Accept-Language' => 'en']);

    $update = $this->withHeader('Authorization', 'Bearer '.$loginData['accessToken'])
        ->patchJson('/api/v1/admin/auth/profile', [
            'name' => 'Safety Admin',
        ], [
            'Accept-Language' => 'en',
        ]);

    $refresh = $this->postJson('/api/v1/admin/auth/refresh', [
        'refreshToken' => $loginData['refreshToken'],
    ], [
        'Accept-Language' => 'en',
    ]);

    $changedPassword = 'SafetyPassword1!';

    $changePassword = $this->withHeader('Authorization', 'Bearer '.$refresh->json('data.accessToken'))
        ->putJson('/api/v1/admin/auth/change-password', [
            'currentPassword' => 'AdminPassword1!',
            'password' => $changedPassword,
            'passwordConfirmation' => $changedPassword,
        ], [
            'Accept-Language' => 'en',
        ]);

    $forgotPassword = $this->postJson('/api/v1/admin/auth/forgot-password', [
        'email' => 'admin@example.test',
    ], [
        'Accept-Language' => 'en',
    ]);

    $user = User::query()->sole();
    PasswordReset::query()->delete();
    PasswordReset::factory()->forUser($user)->create([
        'email_normalized' => $user->email,
        'code_hash' => Hash::make('654321'),
        'code_expires_at' => now()->addMinutes(10),
    ]);

    $verifyCode = $this->postJson('/api/v1/admin/auth/verify-forgot-password-code', [
        'email' => 'admin@example.test',
        'code' => '654321',
    ], [
        'Accept-Language' => 'en',
    ]);

    $resetPassword = $this->postJson('/api/v1/admin/auth/reset-password', [
        'email' => 'admin@example.test',
        'resetToken' => $verifyCode->json('data.resetToken'),
        'password' => 'RecoveredPassword1!',
        'passwordConfirmation' => 'RecoveredPassword1!',
    ], [
        'Accept-Language' => 'en',
    ]);

    $logoutToken = (string) loginAdminForTests([
        'password' => 'RecoveredPassword1!',
    ])->json('data.accessToken');

    $logout = $this->withHeader('Authorization', 'Bearer '.$logoutToken)
        ->postJson('/api/v1/admin/auth/logout', [], [
            'Accept-Language' => 'en',
        ]);

    $responses = [$login, $profile, $update, $refresh, $changePassword, $forgotPassword, $verifyCode, $resetPassword, $logout];

    foreach ($responses as $response) {
        $response->assertSuccessful()
            ->assertHeader('Content-Language', 'en')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache');

        expect(array_keys($response->json()))->toBe(['success', 'message', 'data'])
            ->and($response->headers->get('Vary'))->toContain('Accept-Language')
            ->and($response->json())->not->toHaveKey('code')
            ->and($response->json())->not->toHaveKey('errors');
    }

    expect(array_keys($login->json('data')))->toBe([
        'accessToken',
        'refreshToken',
        'tokenType',
        'tokenExpiresIn',
        'refreshTokenExpiresIn',
        'profile',
    ])->and(array_keys($refresh->json('data')))->toBe([
        'accessToken',
        'refreshToken',
        'tokenType',
        'tokenExpiresIn',
        'refreshTokenExpiresIn',
    ])->and(array_keys($profile->json('data')))->toBe([
        'name',
        'email',
        'avatar',
        'role',
        'permissions',
    ])->and($forgotPassword->json('data'))->toBe([])
        ->and($changePassword->json('data'))->toBe([])
        ->and($resetPassword->json('data'))->toBe([])
        ->and($logout->json('data'))->toBe([]);
});

it('returns the approved failure envelope and required headers across auth failure responses', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $changePasswordAccessToken = (string) loginAdminForTests()->json('data.accessToken');

    $responses = [
        $this->postJson('/api/v1/admin/auth/login', [], ['Accept-Language' => 'en']),
        $this->postJson('/api/v1/admin/auth/refresh', [
            'refreshToken' => 'structurally-valid-but-unknown-token',
        ], ['Accept-Language' => 'en']),
        $this->getJson('/api/v1/admin/auth/profile', ['Accept-Language' => 'en']),
        $this->withHeader('Authorization', 'Bearer '.$changePasswordAccessToken)
            ->putJson('/api/v1/admin/auth/change-password', [
                'currentPassword' => 'WrongPassword1!',
                'password' => 'NewAdminPassword1!',
                'passwordConfirmation' => 'NewAdminPassword1!',
            ], ['Accept-Language' => 'en']),
    ];

    foreach ($responses as $response) {
        expect(array_keys($response->json()))->toBe(['success', 'message', 'code', 'errors'])
            ->and($response->headers->get('Content-Language'))->toBe('en')
            ->and($response->headers->get('Cache-Control'))->toBe('no-store, private')
            ->and($response->headers->get('Pragma'))->toBe('no-cache')
            ->and($response->headers->get('Vary'))->toContain('Accept-Language')
            ->and($response->json())->not->toHaveKey('data');
    }
});
