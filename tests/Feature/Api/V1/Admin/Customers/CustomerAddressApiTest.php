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
        'province' => 'Cairo',
        'city' => 'Nasr City',
        'address' => 'Nasr City | Street 10',
        'notes' => 'Ring bell',
    ], customerAddressAdminHeaders($accessToken));

    $firstAddressResponse->assertCreated()
        ->assertJsonPath('data.isDefault', true)
        ->assertJsonMissingPath('data.addressHash')
        ->assertJsonMissingPath('data.phone');

    $firstAddressId = (int) $firstAddressResponse->json('data.id');

    $secondAddressResponse = $this->postJson('/api/v1/admin/customers/'.$customer->getKey().'/addresses', [
        'province' => 'Giza',
        'city' => 'Dokki',
        'address' => 'Office Street',
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
        ->assertJsonPath('data.0.province', 'Giza')
        ->assertJsonPath('data.0.city', 'Dokki')
        ->assertJsonMissingPath('data.0.phone');
});

it('rejects duplicate active addresses and enforces the 20 active-address limit', function () {
    $accessToken = customerAddressAdminToken();
    $customer = Customer::factory()->create([
        'phone' => '+20 100 444 5555',
        'phone_normalized' => '+201004445555',
    ]);

    $payload = [
        'province' => 'Cairo',
        'city' => 'Maadi',
        'address' => 'Maadi | Street 5',
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
            'province' => 'Province '.$index,
            'city' => 'City '.$index,
            'address' => 'Address '.$index,
            'notes' => null,
            'address_hash' => hash('sha256', mb_strtolower('Province '.$index.'|City '.$index.'|Address '.$index)),
            'is_default' => $index === 1,
        ]);
    }

    $this->postJson('/api/v1/admin/customers/'.$customer->getKey().'/addresses', array_merge($payload, [
        'province' => 'Overflow',
        'city' => 'Overflow City',
        'address' => 'Overflow Street',
    ]), customerAddressAdminHeaders($accessToken))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'CUSTOMER_ADDRESS_LIMIT_EXCEEDED');
});

it('rejects duplicated phone fields in customer address payloads', function () {
    $accessToken = customerAddressAdminToken();
    $customer = Customer::factory()->create();

    $this->postJson('/api/v1/admin/customers/'.$customer->getKey().'/addresses', [
        'phone' => '01001234567',
        'province' => 'Cairo',
        'city' => 'Maadi',
        'address' => 'Street 9',
    ], customerAddressAdminHeaders($accessToken))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['payload']]);

    expect($customer->addresses()->count())->toBe(0);
});

it('reassigns the default to the newest remaining active address when deleting the current default', function () {
    $accessToken = customerAddressAdminToken();
    $customer = Customer::factory()->create([
        'phone' => '+20 100 555 1111',
        'phone_normalized' => '+201005551111',
    ]);

    $oldDefault = $customer->addresses()->create([
        'province' => 'Cairo',
        'city' => 'Nasr City',
        'address' => 'Old Street',
        'notes' => null,
        'address_hash' => hash('sha256', 'cairo|nasr city|old street'),
        'is_default' => true,
    ]);

    $newest = $customer->addresses()->create([
        'province' => 'Giza',
        'city' => 'Dokki',
        'address' => 'Newest Street',
        'notes' => null,
        'address_hash' => hash('sha256', 'giza|dokki|newest street'),
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
        'province' => 'Alex',
        'city' => 'Smouha',
        'address' => 'Street 1',
        'notes' => null,
        'address_hash' => hash('sha256', 'alex|smouha|street 1'),
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
        'province' => 'Cairo',
        'city' => 'Helwan',
        'address' => 'Conflict Street',
        'notes' => null,
        'address_hash' => hash('sha256', 'cairo|helwan|conflict street'),
        'is_default' => true,
    ]);
    $deletedAddress->delete();

    $customer->addresses()->create([
        'province' => 'Cairo',
        'city' => 'Helwan',
        'address' => 'Conflict Street',
        'notes' => null,
        'address_hash' => hash('sha256', 'cairo|helwan|conflict street'),
        'is_default' => false,
    ]);

    $this->postJson('/api/v1/admin/customers/'.$customer->getKey().'/addresses/'.$deletedAddress->getKey().'/restore', [], customerAddressAdminHeaders($accessToken))
        ->assertStatus(409)
        ->assertJsonPath('code', 'CUSTOMER_ADDRESS_RESTORE_CONFLICT');
});
