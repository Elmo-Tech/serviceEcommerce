<?php

declare(strict_types=1);

use App\Mail\AdminPasswordResetCodeMail;
use App\Support\Auth\AuthenticationSecurityLogger;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['api', 'admin.auth.headers'])
        ->prefix('api/v1/admin/auth/_secret-leakage')
        ->group(function (): void {
            Route::get('/explode', function (): never {
                throw new RuntimeException('super-secret-token-value');
            });

            Route::post('/validate', function (Request $request) {
                $request->validate([
                    'password' => ['required', 'min:10'],
                ]);

                return response()->noContent();
            });
        });
});

it('never leaks plaintext auth secrets through responses validation errors exceptions urls or browser-facing headers', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $loginPassword = 'LeakedPassword1!';
    $refreshToken = 'super-secret-refresh-token';
    $resetToken = 'super-secret-reset-token';

    $invalidLogin = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin@example.test',
        'password' => $loginPassword,
    ], [
        'Accept-Language' => 'en',
    ]);

    $invalidRefresh = $this->postJson('/api/v1/admin/auth/refresh', [
        'refreshToken' => $refreshToken,
    ], [
        'Accept-Language' => 'en',
    ]);

    $validationError = $this->postJson('/api/v1/admin/auth/_secret-leakage/validate', [
        'password' => 'short',
    ], [
        'Accept-Language' => 'en',
    ]);

    $resetError = $this->postJson('/api/v1/admin/auth/reset-password', [
        'email' => 'admin@example.test',
        'resetToken' => $resetToken,
        'password' => 'NewAdminPassword1!',
        'passwordConfirmation' => 'NewAdminPassword1!',
    ], [
        'Accept-Language' => 'en',
    ]);

    $exception = $this->getJson('/api/v1/admin/auth/_secret-leakage/explode', [
        'Accept-Language' => 'en',
    ]);

    foreach ([$invalidLogin, $invalidRefresh, $validationError, $resetError, $exception] as $response) {
        expect($response->getContent())->not->toContain($loginPassword)
            ->not->toContain($refreshToken)
            ->not->toContain($resetToken)
            ->not->toContain('super-secret-token-value')
            ->and($response->headers->get('Set-Cookie'))->toBeNull()
            ->and($response->headers->get('Authorization'))->toBeNull()
            ->and($response->headers->get('Location'))->toBeNull();
    }
});

it('renders recovery mail without reset tokens hashes or browser-storage metadata', function () {
    app()->setLocale('en');

    $mail = new AdminPasswordResetCodeMail('654321', 10);
    $rendered = $mail->render();

    expect($rendered)->toContain('654321')
        ->toContain(trans('mail.password_reset_code_expiry', ['minutes' => 10], 'en'))
        ->toContain(trans('mail.password_reset_code_ignore', [], 'en'))
        ->not->toContain('resetToken')
        ->not->toContain(hash('sha256', '654321'))
        ->not->toContain('Authorization')
        ->not->toContain('Bearer')
        ->not->toContain('sessionStorage')
        ->not->toContain('localStorage');
});

it('keeps auth logging and browser-facing backend artifacts free of analytics and monitoring secret sinks', function () {
    Log::spy();

    app(AuthenticationSecurityLogger::class)->warning('admin_auth.scan', [
        'password' => 'SecretPassword1!',
        'token' => 'secret-token',
        'authorization' => 'Bearer secret-token',
        'credentials' => ['password' => 'SecretPassword1!'],
    ]);

    Log::shouldHaveReceived('warning')->once();

    $backendAuthSources = collect([
        file_get_contents(app_path('Actions/Auth/LoginAdminAction.php')),
        file_get_contents(app_path('Actions/Auth/RefreshAdminSessionAction.php')),
        file_get_contents(app_path('Actions/Auth/SendForgotPasswordCodeAction.php')),
        file_get_contents(app_path('Support/Auth/AuthenticationSecurityLogger.php')),
        file_get_contents(base_path('postman/Service-Commerce.postman_collection.json')),
    ])->implode("\n");

    expect($backendAuthSources)->not->toContain('Sentry')
        ->not->toContain('Bugsnag')
        ->not->toContain('analytics.track')
        ->not->toContain('console.log')
        ->not->toContain('window.localStorage')
        ->not->toContain('window.sessionStorage');
});
