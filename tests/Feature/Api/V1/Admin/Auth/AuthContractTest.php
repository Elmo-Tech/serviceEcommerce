<?php

declare(strict_types=1);

use App\Models\PasswordReset;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('keeps the canonical nine operations across eight paths with the expected middleware ownership', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/admin/auth'))
        ->reject(fn ($route) => str_contains($route->uri(), '_'));

    expect($routes)->toHaveCount(9)
        ->and($routes->pluck('uri')->unique()->values()->all())->toBe([
            'api/v1/admin/auth/login',
            'api/v1/admin/auth/refresh',
            'api/v1/admin/auth/forgot-password',
            'api/v1/admin/auth/verify-forgot-password-code',
            'api/v1/admin/auth/reset-password',
            'api/v1/admin/auth/logout',
            'api/v1/admin/auth/profile',
            'api/v1/admin/auth/change-password',
        ]);

    $protectedRoutes = [
        Route::getRoutes()->match(request()->create('/api/v1/admin/auth/logout', 'POST')),
        Route::getRoutes()->match(request()->create('/api/v1/admin/auth/profile', 'GET')),
        Route::getRoutes()->match(request()->create('/api/v1/admin/auth/profile', 'PATCH')),
        Route::getRoutes()->match(request()->create('/api/v1/admin/auth/change-password', 'PUT')),
    ];

    foreach ($protectedRoutes as $route) {
        $middleware = $route->gatherMiddleware();
        $protectedStack = array_values(array_intersect($middleware, [
            'auth:sanctum',
            'admin.user_type',
            'admin.active',
        ]));

        expect($middleware)->toContain('admin.auth.headers')
            ->and($protectedStack)->toBe([
                'auth:sanctum',
                'admin.user_type',
                'admin.active',
            ]);
    }

    $refreshMiddleware = Route::getRoutes()
        ->match(request()->create('/api/v1/admin/auth/refresh', 'POST'))
        ->gatherMiddleware();

    expect($refreshMiddleware)->toContain('admin.auth.headers', 'throttle:admin-refresh')
        ->not->toContain('auth:sanctum', 'admin.user_type', 'admin.active');
});

it('enforces the login and refresh request contracts and returns typed token resources', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $loginResponse = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin@example.test',
        'password' => 'AdminPassword1!',
    ], [
        'Accept-Language' => 'en',
    ]);

    $loginResponse->assertOk()
        ->assertJsonPath('data.tokenType', 'Bearer')
        ->assertJsonPath('data.tokenExpiresIn', 900)
        ->assertJsonPath('data.refreshTokenExpiresIn', 2592000)
        ->assertJsonMissingPath('data.id');

    expect(array_keys($loginResponse->json('data')))->toBe([
        'accessToken',
        'refreshToken',
        'tokenType',
        'tokenExpiresIn',
        'refreshTokenExpiresIn',
        'profile',
    ]);

    $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin@example.test',
        'password' => 'AdminPassword1!',
        'remember' => true,
    ], [
        'Accept-Language' => 'en',
    ])->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    $refreshResponse = $this->postJson('/api/v1/admin/auth/refresh', [
        'refreshToken' => (string) $loginResponse->json('data.refreshToken'),
    ], [
        'Accept-Language' => 'en',
    ]);

    $refreshResponse->assertOk()
        ->assertJsonPath('data.tokenType', 'Bearer')
        ->assertJsonMissingPath('data.profile');

    expect(array_keys($refreshResponse->json('data')))->toBe([
        'accessToken',
        'refreshToken',
        'tokenType',
        'tokenExpiresIn',
        'refreshTokenExpiresIn',
    ]);

    $this->postJson('/api/v1/admin/auth/refresh', [
        'refreshToken' => 'structurally-valid-token',
        'unexpected' => 'value',
    ], [
        'Accept-Language' => 'en',
    ])->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');
});

it('supports multipart method spoofing for profile updates and enforces protected request contracts', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $accessToken = (string) loginAdminForTests()->json('data.accessToken');

    $profileResponse = $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->post('/api/v1/admin/auth/profile', [
            '_method' => 'PATCH',
            'name' => 'Contract Admin',
        ], [
            'Accept-Language' => 'en',
        ]);

    $profileResponse->assertOk()
        ->assertJsonPath('data.name', 'Contract Admin');

    $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->patchJson('/api/v1/admin/auth/profile', [
            'email' => 'evil@example.test',
        ], [
            'Accept-Language' => 'en',
        ])->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    $changePasswordResponse = $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->putJson('/api/v1/admin/auth/change-password', [
            'currentPassword' => 'AdminPassword1!',
            'password' => 'ContractPassword1!',
            'passwordConfirmation' => 'ContractPassword1!',
        ], [
            'Accept-Language' => 'en',
        ]);

    $changePasswordResponse->assertOk()
        ->assertJsonPath('data', []);

    $reloginToken = (string) loginAdminForTests([
        'password' => 'ContractPassword1!',
    ])->json('data.accessToken');

    $this->withHeader('Authorization', 'Bearer '.$reloginToken)
        ->putJson('/api/v1/admin/auth/change-password', [
            'currentPassword' => 'ContractPassword1!',
            'password' => 'AnotherPassword1!',
            'passwordConfirmation' => 'AnotherPassword1!',
            'unexpected' => 'value',
        ], [
            'Accept-Language' => 'en',
        ])->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');
});

it('enforces forgot-password recovery request contracts and returns typed success payloads', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $forgotPasswordResponse = $this->postJson('/api/v1/admin/auth/forgot-password', [
        'email' => 'admin@example.test',
    ], [
        'Accept-Language' => 'en',
    ]);

    $forgotPasswordResponse->assertOk()
        ->assertJsonPath('data', []);

    $this->postJson('/api/v1/admin/auth/forgot-password', [
        'email' => 'admin@example.test',
        'unexpected' => 'value',
    ], [
        'Accept-Language' => 'en',
    ])->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    $user = User::query()->sole();
    PasswordReset::factory()->forUser($user)->create([
        'email_normalized' => $user->email,
        'code_hash' => Hash::make('654321'),
        'code_expires_at' => now()->addMinutes(10),
    ]);

    $verifyResponse = $this->postJson('/api/v1/admin/auth/verify-forgot-password-code', [
        'email' => 'admin@example.test',
        'code' => '654321',
    ], [
        'Accept-Language' => 'en',
    ]);

    $verifyResponse->assertOk()
        ->assertJsonPath('data.resetTokenExpiresIn', 600);

    expect(array_keys($verifyResponse->json('data')))->toBe([
        'resetToken',
        'resetTokenExpiresIn',
    ]);

    $this->postJson('/api/v1/admin/auth/verify-forgot-password-code', [
        'email' => 'admin@example.test',
        'code' => '654321',
        'unexpected' => 'value',
    ], [
        'Accept-Language' => 'en',
    ])->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    $resetResponse = $this->postJson('/api/v1/admin/auth/reset-password', [
        'email' => 'admin@example.test',
        'resetToken' => (string) $verifyResponse->json('data.resetToken'),
        'password' => 'RecoveredPassword1!',
        'passwordConfirmation' => 'RecoveredPassword1!',
    ], [
        'Accept-Language' => 'en',
    ]);

    $resetResponse->assertOk()
        ->assertJsonPath('data', []);

    $this->postJson('/api/v1/admin/auth/reset-password', [
        'email' => 'admin@example.test',
        'resetToken' => 'another-token',
        'password' => 'RecoveredPassword1!',
        'passwordConfirmation' => 'RecoveredPassword1!',
        'unexpected' => 'value',
    ], [
        'Accept-Language' => 'en',
    ])->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    $logoutToken = (string) loginAdminForTests([
        'password' => 'RecoveredPassword1!',
    ])->json('data.accessToken');

    $this->withHeader('Authorization', 'Bearer '.$logoutToken)
        ->postJson('/api/v1/admin/auth/logout', [], [
            'Accept-Language' => 'en',
        ])->assertOk()
        ->assertJsonPath('data', []);
});
