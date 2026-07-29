<?php

declare(strict_types=1);

use App\Actions\CustomerAddresses\ResolveGuestCustomerAddressAction;
use App\Exceptions\ApiBusinessException;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reuses an active matching address and does not create a duplicate record', function () {
    $customer = Customer::factory()->create([
        'phone' => '+20 100 888 1111',
        'phone_normalized' => '+201008881111',
    ]);

    $address = $customer->addresses()->create([
        'label' => 'Saved Address',
        'phone' => '+20 100 888 1111',
        'phone_normalized' => '+201008881111',
        'country_code' => 'EG',
        'city' => 'Cairo',
        'area' => 'Maadi',
        'street' => 'Street 9',
        'notes' => 'Saved notes',
        'address_hash' => hash('sha256', 'eg|cairo|maadi|street 9'),
        'is_default' => true,
    ]);

    $result = app(ResolveGuestCustomerAddressAction::class)->execute($customer, [
        'label' => 'Guest Label',
        'phone' => '01008881111',
        'phoneCountryCode' => 'EG',
        'countryCode' => 'EG',
        'city' => 'Cairo',
        'area' => 'Maadi',
        'street' => 'Street 9',
        'notes' => 'Guest notes',
    ]);

    expect($result['wasCreated'])->toBeFalse()
        ->and($result['wasRestored'])->toBeFalse()
        ->and($result['address']->getKey())->toBe($address->getKey())
        ->and($customer->addresses()->count())->toBe(1);
});

it('restores a soft-deleted matching address and rejects new unmatched addresses when the active limit is reached', function () {
    $customer = Customer::factory()->create([
        'phone' => '+20 100 888 2222',
        'phone_normalized' => '+201008882222',
    ]);

    $deletedAddress = $customer->addresses()->create([
        'label' => 'Deleted Address',
        'phone' => '+20 100 888 2222',
        'phone_normalized' => '+201008882222',
        'country_code' => 'EG',
        'city' => 'Giza',
        'area' => null,
        'street' => 'Restore Street',
        'notes' => null,
        'address_hash' => hash('sha256', 'eg|giza||restore street'),
        'is_default' => true,
    ]);
    $deletedAddress->delete();

    $restoreResult = app(ResolveGuestCustomerAddressAction::class)->execute($customer, [
        'label' => 'Guest Label',
        'phone' => '01008882222',
        'phoneCountryCode' => 'EG',
        'countryCode' => 'EG',
        'city' => 'Giza',
        'area' => null,
        'street' => 'Restore Street',
        'notes' => null,
    ]);

    expect($restoreResult['wasCreated'])->toBeFalse()
        ->and($restoreResult['wasRestored'])->toBeTrue()
        ->and($deletedAddress->fresh()->trashed())->toBeFalse();

    foreach (range(1, 19) as $index) {
        $customer->addresses()->create([
            'label' => 'Address '.$index,
            'phone' => '+20 100 888 2222',
            'phone_normalized' => '+201008882222',
            'country_code' => 'EG',
            'city' => 'City '.$index,
            'area' => null,
            'street' => 'Street '.$index,
            'notes' => null,
            'address_hash' => hash('sha256', 'eg|city '.$index.'||street '.$index),
            'is_default' => $index === 1,
        ]);
    }

    app(ResolveGuestCustomerAddressAction::class)->execute($customer, [
        'label' => 'Overflow',
        'phone' => '01008882222',
        'phoneCountryCode' => 'EG',
        'countryCode' => 'EG',
        'city' => 'Overflow',
        'area' => null,
        'street' => 'Overflow Street',
        'notes' => null,
    ]);
})->throws(ApiBusinessException::class, 'CUSTOMER_ADDRESS_LIMIT_EXCEEDED');
