<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);
});

function customerAddressAdminHeaders(string $accessToken, string $locale = 'en'): array
{
    return [
        'Authorization' => 'Bearer '.$accessToken,
        'Accept-Language' => $locale,
    ];
}

function customerAddressAdminToken(array $credentials = []): string
{
    return (string) loginAdminForTests($credentials)->json('data.accessToken');
}

it('creates customer addresses, enforces one default, and supports localized nested responses', function () {
    $accessToken = customerAddressAdminToken();
    $customer = Customer::factory()->create([
        'phone' => '+20 100 333 4444',
        'phone_normalized' => '+201003334444',
    ]);

    $firstAddressResponse = $this->postJson('/api/v1/admin/customers/'.$customer->getKey().'/addresses', [
        'label' => 'Home',
        'phone' => '01003334444',
        'phoneCountryCode' => 'EG',
        'countryCode' => 'EG',
        'city' => 'Cairo',
        'area' => 'Nasr City',
        'street' => 'Street 10',
        'notes' => 'Ring bell',
    ], customerAddressAdminHeaders($accessToken));

    $firstAddressResponse->assertCreated()
        ->assertJsonPath('data.phone', '01003334444')
        ->assertJsonPath('data.isDefault', true)
        ->assertJsonMissingPath('data.addressHash')
        ->assertJsonMissingPath('data.phoneNormalized');

    $firstAddressId = (int) $firstAddressResponse->json('data.id');

    $secondAddressResponse = $this->postJson('/api/v1/admin/customers/'.$customer->getKey().'/addresses', [
        'label' => 'Office',
        'phone' => '01003334444',
        'phoneCountryCode' => 'EG',
        'countryCode' => 'EG',
        'city' => 'Giza',
        'area' => null,
        'street' => 'Office Street',
        'notes' => null,
        'isDefault' => true,
    ], customerAddressAdminHeaders($accessToken, 'ar'));

    $secondAddressResponse->assertCreated()
        ->assertHeader('Content-Language', 'ar')
        ->assertJsonPath('data.isDefault', true);

    expect($customer->addresses()->findOrFail($firstAddressId)->fresh()->is_default)->toBeFalse();

    $listResponse = $this->getJson('/api/v1/admin/customers/'.$customer->getKey().'/addresses?filter[status]=all', customerAddressAdminHeaders($accessToken));

    $listResponse->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.label', 'Office')
        ->assertJsonPath('data.0.phone', '01003334444');
});

it('rejects duplicate active addresses and enforces the 20 active-address limit', function () {
    $accessToken = customerAddressAdminToken();
    $customer = Customer::factory()->create([
        'phone' => '+20 100 444 5555',
        'phone_normalized' => '+201004445555',
    ]);

    $payload = [
        'label' => 'Home',
        'phone' => '01004445555',
        'phoneCountryCode' => 'EG',
        'countryCode' => 'EG',
        'city' => 'Cairo',
        'area' => 'Maadi',
        'street' => 'Street 5',
        'notes' => null,
    ];

    $this->postJson('/api/v1/admin/customers/'.$customer->getKey().'/addresses', $payload, customerAddressAdminHeaders($accessToken))
        ->assertCreated();

    $this->postJson('/api/v1/admin/customers/'.$customer->getKey().'/addresses', $payload, customerAddressAdminHeaders($accessToken))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'CUSTOMER_ADDRESS_ALREADY_EXISTS');

    $customer = Customer::factory()->create([
        'phone' => '+20 100 444 6666',
        'phone_normalized' => '+201004446666',
    ]);

    foreach (range(1, 20) as $index) {
        $customer->addresses()->create([
            'label' => 'Address '.$index,
            'phone' => '+20 100 444 6666',
            'phone_normalized' => '+201004446666',
            'country_code' => 'EG',
            'city' => 'City '.$index,
            'area' => null,
            'street' => 'Street '.$index,
            'notes' => null,
            'address_hash' => hash('sha256', 'eg|city '.$index.'||street '.$index),
            'is_default' => $index === 1,
        ]);
    }

    $this->postJson('/api/v1/admin/customers/'.$customer->getKey().'/addresses', array_merge($payload, [
        'city' => 'Overflow City',
        'street' => 'Overflow Street',
    ]), customerAddressAdminHeaders($accessToken))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'CUSTOMER_ADDRESS_LIMIT_EXCEEDED');
});

it('reassigns the default to the newest remaining active address when deleting the current default', function () {
    $accessToken = customerAddressAdminToken();
    $customer = Customer::factory()->create([
        'phone' => '+20 100 555 1111',
        'phone_normalized' => '+201005551111',
    ]);

    $oldDefault = $customer->addresses()->create([
        'label' => 'Old Default',
        'phone' => '+20 100 555 1111',
        'phone_normalized' => '+201005551111',
        'country_code' => 'EG',
        'city' => 'Cairo',
        'area' => null,
        'street' => 'Old Street',
        'notes' => null,
        'address_hash' => hash('sha256', 'eg|cairo||old street'),
        'is_default' => true,
    ]);

    $newest = $customer->addresses()->create([
        'label' => 'Newest',
        'phone' => '+20 100 555 1111',
        'phone_normalized' => '+201005551111',
        'country_code' => 'EG',
        'city' => 'Giza',
        'area' => null,
        'street' => 'Newest Street',
        'notes' => null,
        'address_hash' => hash('sha256', 'eg|giza||newest street'),
        'is_default' => false,
    ]);

    $this->deleteJson('/api/v1/admin/customers/'.$customer->getKey().'/addresses/'.$oldDefault->getKey(), [], customerAddressAdminHeaders($accessToken))
        ->assertOk();

    expect($newest->fresh()->is_default)->toBeTrue()
        ->and($oldDefault->fresh()->trashed())->toBeTrue();
});

it('returns scoped 404s for nested address routes outside the customer scope', function () {
    $accessToken = customerAddressAdminToken();
    $customer = Customer::factory()->create([
        'phone' => '+20 100 666 1111',
        'phone_normalized' => '+201006661111',
    ]);
    $otherCustomer = Customer::factory()->create([
        'phone' => '+20 100 666 2222',
        'phone_normalized' => '+201006662222',
    ]);

    $address = $otherCustomer->addresses()->create([
        'label' => 'Other',
        'phone' => '+20 100 666 2222',
        'phone_normalized' => '+201006662222',
        'country_code' => 'EG',
        'city' => 'Alex',
        'area' => null,
        'street' => 'Street 1',
        'notes' => null,
        'address_hash' => hash('sha256', 'eg|alex||street 1'),
        'is_default' => true,
    ]);

    $this->getJson('/api/v1/admin/customers/'.$customer->getKey().'/addresses/'.$address->getKey(), customerAddressAdminHeaders($accessToken))
        ->assertNotFound()
        ->assertJsonPath('code', 'CUSTOMER_ADDRESS_NOT_FOUND');
});

it('returns forbidden when an authenticated administrator lacks address permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $customer = Customer::factory()->create([
        'phone' => '+20 100 666 1111',
        'phone_normalized' => '+201006661111',
    ]);

    $forbiddenAdmin = User::factory()->administrator()->create([
        'email' => 'address-forbidden-admin@example.test',
        'password' => Hash::make('Password123!'),
    ]);

    $forbiddenToken = $forbiddenAdmin->createToken('customer-addresses-forbidden-test')->plainTextToken;

    $this->getJson(
        '/api/v1/admin/customers/'.$customer->getKey().'/addresses',
        customerAddressAdminHeaders($forbiddenToken),
    )
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('returns restore conflicts when a deleted address collides with an active duplicate', function () {
    $accessToken = customerAddressAdminToken();
    $customer = Customer::factory()->create([
        'phone' => '+20 100 666 1111',
        'phone_normalized' => '+201006661111',
    ]);

    $deletedAddress = $customer->addresses()->create([
        'label' => 'Deleted',
        'phone' => '+20 100 666 1111',
        'phone_normalized' => '+201006661111',
        'country_code' => 'EG',
        'city' => 'Cairo',
        'area' => null,
        'street' => 'Conflict Street',
        'notes' => null,
        'address_hash' => hash('sha256', 'eg|cairo||conflict street'),
        'is_default' => true,
    ]);
    $deletedAddress->delete();

    $customer->addresses()->create([
        'label' => 'Active Duplicate',
        'phone' => '+20 100 666 1111',
        'phone_normalized' => '+201006661111',
        'country_code' => 'EG',
        'city' => 'Cairo',
        'area' => null,
        'street' => 'Conflict Street',
        'notes' => null,
        'address_hash' => hash('sha256', 'eg|cairo||conflict street'),
        'is_default' => false,
    ]);

    $this->postJson('/api/v1/admin/customers/'.$customer->getKey().'/addresses/'.$deletedAddress->getKey().'/restore', [], customerAddressAdminHeaders($accessToken))
        ->assertStatus(409)
        ->assertJsonPath('code', 'CUSTOMER_ADDRESS_RESTORE_CONFLICT');
});
