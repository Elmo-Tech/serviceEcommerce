<?php

declare(strict_types=1);

use App\Support\Api\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['api', 'admin.auth.headers'])
        ->prefix('api/v1/admin/auth/_localization')
        ->group(function (): void {
            Route::get('/success', fn () => ApiResponse::success(__('auth.login_success'), ['ok' => true]));

            Route::post('/validate', function (Request $request) {
                $request->validate([
                    'email' => ['required', 'email'],
                ]);

                return ApiResponse::success(__('auth.login_success'), [
                    'email' => $request->string('email')->toString(),
                ]);
            });
        });
});

it('resolves locale before validation and returns required authentication headers on validation failures', function () {
    $response = $this->postJson('/api/v1/admin/auth/_localization/validate', [], [
        'Accept-Language' => 'en-US,en;q=0.9',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonPath('message', trans('validation.invalid_payload', [], 'en'))
        ->assertHeader('Content-Language', 'en')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Pragma', 'no-cache');

    expect($response->headers->get('Vary'))->toContain('Accept-Language');
});

it('uses arabic fallback locale and preserves authentication headers on success responses', function () {
    config()->set('app.locale', 'ar');

    $response = $this->getJson('/api/v1/admin/auth/_localization/success', [
        'Accept-Language' => 'fr-FR',
    ]);

    $response->assertOk()
        ->assertJsonPath('message', trans('auth.login_success', [], 'ar'))
        ->assertHeader('Content-Language', 'ar')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Pragma', 'no-cache');

    expect($response->headers->get('Vary'))->toContain('Accept-Language');
});
