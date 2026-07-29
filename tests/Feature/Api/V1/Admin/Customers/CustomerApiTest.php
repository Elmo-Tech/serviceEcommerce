<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);
});

function customerAdminHeaders(string $accessToken, string $locale = 'en'): array
{
    return [
        'Authorization' => 'Bearer '.$accessToken,
        'Accept-Language' => $locale,
    ];
}

function customerAdminToken(array $credentials = []): string
{
    return (string) loginAdminForTests($credentials)->json('data.accessToken');
}

it('creates customers, lists them with filters, and returns only approved safe fields', function () {
    $accessToken = customerAdminToken();

    $createResponse = $this->postJson('/api/v1/admin/customers', [
        'name' => '  Mohamed   Hassan  ',
        'email' => '  MOHAMED@example.com ',
        'phone' => '01001234567',
        'phoneCountryCode' => 'eg',
    ], customerAdminHeaders($accessToken));

    $createResponse->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Mohamed Hassan')
        ->assertJsonPath('data.email', 'mohamed@example.com')
        ->assertJsonPath('data.isDeleted', false)
        ->assertJsonPath('data.deletedAt', null)
        ->assertJsonPath('data.addressesCount', 0)
        ->assertJsonPath('data.addresses', [])
        ->assertJsonMissingPath('data.phoneNormalized')
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.tokens');

    $customer = Customer::query()->sole();

    expect($customer->phone_normalized)->toBe('+201001234567')
        ->and($customer->phone)->toStartWith('+20');

    $withAddress = Customer::factory()->create([
        'name' => 'Ziad Ali',
        'phone' => '+20 100 555 6666',
        'phone_normalized' => '+201005556666',
    ]);

    $withAddress->addresses()->create([
        'label' => 'Home',
        'phone' => '+20 100 555 6666',
        'phone_normalized' => '+201005556666',
        'country_code' => 'EG',
        'city' => 'Cairo',
        'area' => 'Nasr City',
        'street' => 'Street 10',
        'notes' => null,
        'address_hash' => hash('sha256', 'eg|cairo|nasr city|street 10'),
        'is_default' => true,
    ]);

    $listResponse = $this->getJson('/api/v1/admin/customers?filter[status]=all&filter[hasAddresses]=true&sort=name', customerAdminHeaders($accessToken));

    $listResponse->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.currentPage', 1)
        ->assertJsonPath('meta.perPage', 20)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.lastPage', 1)
        ->assertJsonPath('data.0.name', 'Ziad Ali')
        ->assertJsonPath('data.0.addressesCount', 1)
        ->assertJsonMissingPath('data.0.phoneNormalized');

    $showResponse = $this->getJson('/api/v1/admin/customers/'.$customer->getKey(), customerAdminHeaders($accessToken));

    $showResponse->assertOk()
        ->assertJsonPath('data.id', $customer->getKey())
        ->assertJsonPath('data.addressesCount', 0)
        ->assertJsonMissingPath('data.phoneNormalized');
});

it('rejects duplicate normalized phone numbers and duplicate normalized emails', function () {
    $accessToken = customerAdminToken();

    Customer::factory()->create([
        'name' => 'Existing Customer',
        'email' => 'existing@example.com',
        'phone' => '+20 100 123 4567',
        'phone_normalized' => '+201001234567',
    ]);

    $duplicatePhoneResponse = $this->postJson('/api/v1/admin/customers', [
        'name' => 'Duplicate Phone',
        'email' => 'another@example.com',
        'phone' => '01001234567',
        'phoneCountryCode' => 'EG',
    ], customerAdminHeaders($accessToken));

    $duplicatePhoneResponse->assertUnprocessable()
        ->assertJsonPath('code', 'CUSTOMER_PHONE_ALREADY_EXISTS')
        ->assertJsonStructure(['errors' => ['phone']]);

    $duplicateEmailResponse = $this->postJson('/api/v1/admin/customers', [
        'name' => 'Duplicate Email',
        'email' => ' EXISTING@example.com ',
        'phone' => '01004567890',
        'phoneCountryCode' => 'EG',
    ], customerAdminHeaders($accessToken));

    $duplicateEmailResponse->assertUnprocessable()
        ->assertJsonPath('code', 'CUSTOMER_EMAIL_ALREADY_EXISTS')
        ->assertJsonStructure(['errors' => ['email']]);
});

it('soft deletes customers with their active addresses and restores only the customer record', function () {
    $accessToken = customerAdminToken();

    $customer = Customer::factory()->create([
        'phone' => '+20 100 111 2222',
        'phone_normalized' => '+201001112222',
    ]);

    $address = $customer->addresses()->create([
        'label' => 'Home',
        'phone' => '+20 100 111 2222',
        'phone_normalized' => '+201001112222',
        'country_code' => 'EG',
        'city' => 'Cairo',
        'area' => 'Heliopolis',
        'street' => 'Street 1',
        'notes' => null,
        'address_hash' => hash('sha256', 'eg|cairo|heliopolis|street 1'),
        'is_default' => true,
    ]);

    $this->deleteJson('/api/v1/admin/customers/'.$customer->getKey(), [], customerAdminHeaders($accessToken))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($customer->fresh()->trashed())->toBeTrue()
        ->and($address->fresh()->trashed())->toBeTrue();

    $restoreResponse = $this->postJson('/api/v1/admin/customers/'.$customer->getKey().'/restore', [], customerAdminHeaders($accessToken));

    $restoreResponse->assertOk()
        ->assertJsonPath('data.id', $customer->getKey())
        ->assertJsonPath('data.isDeleted', false);

    expect($customer->fresh()->trashed())->toBeFalse()
        ->and($address->fresh()->trashed())->toBeTrue();
});

it('returns the approved representative authentication and permission boundaries for customer routes', function () {
    $this->getJson('/api/v1/admin/customers', [
        'Accept-Language' => 'en',
    ])->assertUnauthorized()
        ->assertJsonPath('code', 'UNAUTHENTICATED');

    $this->seed(RolesAndPermissionsSeeder::class);

    $inactiveAdmin = User::factory()->administrator()->inactive()->create([
        'email' => 'inactive-admin@example.test',
        'password' => Hash::make('Password123!'),
    ]);

    Sanctum::actingAs($inactiveAdmin);

    $this->getJson('/api/v1/admin/customers', [
        'Accept-Language' => 'en',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'USER_INACTIVE');

    $forbiddenAdmin = User::factory()->administrator()->create([
        'email' => 'forbidden-admin@example.test',
        'password' => Hash::make('Password123!'),
    ]);

    Sanctum::actingAs($forbiddenAdmin);

    $this->getJson('/api/v1/admin/customers', [
        'Accept-Language' => 'en',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');
});
