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
        'phone' => '+20 100 888 1111',
        'phone_normalized' => '+201008881111',
        'province' => 'Cairo',
        'city' => 'Maadi',
        'address' => 'Maadi | Street 9',
        'notes' => 'Saved notes',
        'address_hash' => hash('sha256', 'cairo|maadi|maadi | street 9'),
        'is_default' => true,
    ]);

    $result = app(ResolveGuestCustomerAddressAction::class)->execute($customer, [
        'phone' => '01008881111',
        'phoneCountryCode' => 'EG',
        'province' => 'Cairo',
        'city' => 'Maadi',
        'address' => 'Maadi | Street 9',
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
        'phone' => '+20 100 888 2222',
        'phone_normalized' => '+201008882222',
        'province' => 'Giza',
        'city' => 'Dokki',
        'address' => 'Restore Street',
        'notes' => null,
        'address_hash' => hash('sha256', 'giza|dokki|restore street'),
        'is_default' => true,
    ]);
    $deletedAddress->delete();

    $restoreResult = app(ResolveGuestCustomerAddressAction::class)->execute($customer, [
        'phone' => '01008882222',
        'phoneCountryCode' => 'EG',
        'province' => 'Giza',
        'city' => 'Dokki',
        'address' => 'Restore Street',
        'notes' => null,
    ]);

    expect($restoreResult['wasCreated'])->toBeFalse()
        ->and($restoreResult['wasRestored'])->toBeTrue()
        ->and($deletedAddress->fresh()->trashed())->toBeFalse();

    foreach (range(1, 19) as $index) {
        $customer->addresses()->create([
            'phone' => '+20 100 888 2222',
            'phone_normalized' => '+201008882222',
            'province' => 'Province '.$index,
            'city' => 'City '.$index,
            'address' => 'Address '.$index,
            'notes' => null,
            'address_hash' => hash('sha256', mb_strtolower('Province '.$index.'|City '.$index.'|Address '.$index)),
            'is_default' => $index === 1,
        ]);
    }

    app(ResolveGuestCustomerAddressAction::class)->execute($customer, [
        'phone' => '01008882222',
        'phoneCountryCode' => 'EG',
        'province' => 'Overflow',
        'city' => 'Overflow City',
        'address' => 'Overflow Street',
        'notes' => null,
    ]);
})->throws(ApiBusinessException::class, 'CUSTOMER_ADDRESS_LIMIT_EXCEEDED');
