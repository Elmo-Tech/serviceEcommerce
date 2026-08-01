<?php

declare(strict_types=1);

use App\Models\Customer;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);
});

function customerFeatureHeaders(string $accessToken, string $locale = 'en'): array
{
    return [
        'Authorization' => 'Bearer '.$accessToken,
        'Accept-Language' => $locale,
    ];
}

it('records the exact customer feature routes with the approved middleware order and independent permissions', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/admin/customers'));

    $signatures = $routes
        ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
        ->sort()
        ->values()
        ->all();

    expect($signatures)->toBe([
        'DELETE api/v1/admin/customers/{customer}',
        'DELETE api/v1/admin/customers/{customer}/addresses/{address}',
        'GET|HEAD api/v1/admin/customers',
        'GET|HEAD api/v1/admin/customers/{customer}',
        'GET|HEAD api/v1/admin/customers/{customer}/addresses',
        'GET|HEAD api/v1/admin/customers/{customer}/addresses/{address}',
        'PATCH api/v1/admin/customers/{customer}',
        'PATCH api/v1/admin/customers/{customer}/addresses/{address}',
        'POST api/v1/admin/customers',
        'POST api/v1/admin/customers/{customer}/addresses',
        'POST api/v1/admin/customers/{customer}/addresses/{address}/restore',
        'POST api/v1/admin/customers/{customer}/restore',
        'PUT api/v1/admin/customers/{customer}/addresses/{address}/default',
    ]);

    $customerListRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/customers', 'GET'));
    $customerStoreRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/customers', 'POST'));
    $customerRestoreRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/customers/1/restore', 'POST'));
    $addressListRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/customers/1/addresses', 'GET'));
    $addressDefaultRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/customers/1/addresses/2/default', 'PUT'));

    $protectedStack = ['auth:sanctum', 'admin.user_type', 'admin.active'];

    foreach ([$customerListRoute, $customerStoreRoute, $customerRestoreRoute, $addressListRoute, $addressDefaultRoute] as $route) {
        $middleware = $route->gatherMiddleware();
        $stack = array_values(array_intersect($middleware, $protectedStack));

        expect($middleware)->toContain('admin.auth.headers')
            ->and($stack)->toBe($protectedStack);
    }

    expect($customerListRoute->gatherMiddleware())->toContain('permission:customers.view')
        ->and($customerStoreRoute->gatherMiddleware())->toContain('permission:customers.create')
        ->and($customerRestoreRoute->gatherMiddleware())->toContain('permission:customers.restore')
        ->and($addressListRoute->gatherMiddleware())->toContain('permission:customer-addresses.view')
        ->and($addressDefaultRoute->gatherMiddleware())->toContain('permission:customer-addresses.set-default');
});

it('keeps feature 002 outside public customer auth or public customer crud surfaces and rejects token fields in payloads', function () {
    $uris = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->uri())
        ->values();

    expect($uris->filter(fn (string $uri) => str_starts_with($uri, 'api/v1/public/customers')))->toBeEmpty()
        ->and($uris->filter(fn (string $uri) => str_contains($uri, 'api/v1/customer/auth')))->toBeEmpty()
        ->and($uris->filter(fn (string $uri) => str_contains($uri, 'api/v1/public/customer-auth')))->toBeEmpty();

    $accessToken = (string) loginAdminForTests()->json('data.accessToken');

    $this->postJson('/api/v1/admin/customers', [
        'name' => 'Architecture Customer',
        'phone' => '01007778888',
        'phoneCountryCode' => 'EG',
        'accessToken' => 'fake-access-token',
        'refreshToken' => 'fake-refresh-token',
    ], customerFeatureHeaders($accessToken))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['payload']]);
});

it('returns safe customer and address resources and localizes customer feature responses in english and arabic', function () {
    $accessToken = (string) loginAdminForTests()->json('data.accessToken');

    $customer = Customer::factory()->create([
        'phone' => '+20 100 999 1111',
        'phone_normalized' => '+201009991111',
    ]);

    $customer->addresses()->create([
        'phone' => '+20 100 999 1111',
        'phone_normalized' => '+201009991111',
        'province' => 'Cairo',
        'city' => 'Maadi',
        'address' => 'Maadi | Street 9',
        'notes' => 'Near the club',
        'address_hash' => hash('sha256', 'cairo|maadi|maadi | street 9'),
        'is_default' => true,
    ]);

    $englishCustomerResponse = $this->getJson(
        '/api/v1/admin/customers/'.$customer->getKey(),
        customerFeatureHeaders($accessToken, 'en'),
    );

    $englishCustomerResponse->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('message', 'Customer retrieved successfully.')
        ->assertJsonMissingPath('data.phoneNormalized')
        ->assertJsonMissingPath('data.accessToken')
        ->assertJsonMissingPath('data.refreshToken')
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.addresses.0.phoneNormalized')
        ->assertJsonMissingPath('data.addresses.0.addressHash');

    $englishAddressResponse = $this->getJson(
        '/api/v1/admin/customers/'.$customer->getKey().'/addresses',
        customerFeatureHeaders($accessToken, 'en'),
    );

    $arabicAddressResponse = $this->getJson(
        '/api/v1/admin/customers/'.$customer->getKey().'/addresses',
        customerFeatureHeaders($accessToken, 'ar'),
    );

    $englishAddressResponse->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('message', 'Customer addresses retrieved successfully.')
        ->assertJsonMissingPath('data.0.phoneNormalized')
        ->assertJsonMissingPath('data.0.addressHash');

    $arabicAddressResponse->assertOk()
        ->assertHeader('Content-Language', 'ar');

    expect($arabicAddressResponse->json('message'))
        ->not->toBe($englishAddressResponse->json('message'));
});
