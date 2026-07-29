<?php

declare(strict_types=1);

use App\Enums\HttpStatusCode;
use App\Support\Api\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['api', 'admin.auth.headers'])
        ->prefix('api/v1/admin/auth/_foundation')
        ->group(function (): void {
            Route::get('/success', fn () => ApiResponse::success(__('auth.login_success'), ['ok' => true]));
            Route::post('/created', fn () => ApiResponse::success(__('auth.login_success'), ['created' => true], HttpStatusCode::CREATED));

            Route::post('/validate', function (Request $request) {
                $request->validate([
                    'email' => ['required', 'email'],
                ]);

                return ApiResponse::success(__('auth.login_success'), [
                    'email' => $request->string('email')->toString(),
                ]);
            });

            Route::get('/protected', fn () => ApiResponse::success('ok', null))
                ->middleware('auth:sanctum');

            Route::get('/explode', function (): never {
                throw new RuntimeException('leaked-secret-token-value');
            });
        });
});

it('selects explicit response statuses through the shared http status enum', function () {
    $response = $this->postJson('/api/v1/admin/auth/_foundation/created', [], [
        'Accept-Language' => 'en',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.created', true);
});

it('applies the shared response envelope, locale resolution, and authentication headers', function () {
    $response = $this->getJson('/api/v1/admin/auth/_foundation/success', [
        'Accept-Language' => 'en-US,en;q=0.9',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => trans('auth.login_success', [], 'en'),
            'data' => ['ok' => true],
        ])
        ->assertHeader('Content-Language', 'en')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Pragma', 'no-cache');

    expect($response->headers->get('Vary'))->toContain('Accept-Language');

    config()->set('app.locale', 'ar');

    $defaultLocaleResponse = $this->getJson('/api/v1/admin/auth/_foundation/success', [
        'Accept-Language' => 'fr-FR',
    ]);

    $defaultLocaleResponse->assertOk()
        ->assertJsonPath('message', trans('auth.login_success', [], 'ar'))
        ->assertHeader('Content-Language', 'ar');
});

it('renders validation failures as localized api envelopes before business logic continues', function () {
    $response = $this->postJson('/api/v1/admin/auth/_foundation/validate', [], [
        'Accept-Language' => 'en',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', trans('validation.invalid_payload', [], 'en'))
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonStructure([
            'errors' => ['email'],
        ])
        ->assertHeader('Content-Language', 'en')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('renders unauthenticated errors with the shared api envelope on protected auth routes', function () {
    $response = $this->getJson('/api/v1/admin/auth/_foundation/protected', [
        'Accept-Language' => 'en',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', trans('auth.unauthenticated', [], 'en'))
        ->assertJsonPath('code', 'UNAUTHENTICATED')
        ->assertJsonPath('errors', null)
        ->assertHeader('Content-Language', 'en')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('renders method-not-allowed and not-found failures as stable api errors', function () {
    $this->putJson('/api/v1/admin/auth/_foundation/success', [], [
        'Accept-Language' => 'en',
    ])->assertStatus(405)
        ->assertJsonPath('code', 'METHOD_NOT_ALLOWED')
        ->assertJsonPath('message', trans('auth.method_not_allowed', [], 'en'));

    $this->getJson('/api/v1/admin/auth/_foundation/missing', [
        'Accept-Language' => 'en',
    ])->assertStatus(404)
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND')
        ->assertJsonPath('message', trans('auth.resource_not_found', [], 'en'));
});

it('never leaks internal exception secrets through the shared api error envelope', function () {
    $response = $this->getJson('/api/v1/admin/auth/_foundation/explode', [
        'Accept-Language' => 'en',
    ]);

    $response->assertStatus(500)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', trans('auth.server_error', [], 'en'))
        ->assertJsonPath('code', 'INTERNAL_SERVER_ERROR')
        ->assertJsonPath('errors', null)
        ->assertHeader('Content-Language', 'en');

    expect($response->getContent())->not->toContain('leaked-secret-token-value');
});
