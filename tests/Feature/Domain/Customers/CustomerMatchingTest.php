<?php

declare(strict_types=1);

use App\Actions\Customers\ResolveGuestCustomerAction;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reuses an active matching customer without overwriting saved name or email', function () {
    $customer = Customer::factory()->create([
        'name' => 'Saved Customer',
        'email' => 'saved@example.com',
        'phone' => '+20 100 777 1111',
        'phone_normalized' => '+201007771111',
    ]);

    $result = app(ResolveGuestCustomerAction::class)->execute([
        'name' => 'Guest Provided Name',
        'email' => 'guest@example.com',
        'phone' => '01007771111',
        'phoneCountryCode' => 'EG',
    ]);

    expect($result['wasCreated'])->toBeFalse()
        ->and($result['wasRestored'])->toBeFalse()
        ->and($result['customer']->is($customer))->toBeTrue()
        ->and($customer->fresh()->name)->toBe('Saved Customer')
        ->and($customer->fresh()->email)->toBe('saved@example.com');
});

it('restores a soft-deleted matching customer instead of creating a duplicate', function () {
    $customer = Customer::factory()->deleted()->create([
        'name' => 'Deleted Customer',
        'email' => 'deleted@example.com',
        'phone' => '+20 100 777 2222',
        'phone_normalized' => '+201007772222',
    ]);

    $result = app(ResolveGuestCustomerAction::class)->execute([
        'name' => 'Guest Name',
        'email' => 'guest@example.com',
        'phone' => '01007772222',
        'phoneCountryCode' => 'EG',
    ]);

    expect($result['wasCreated'])->toBeFalse()
        ->and($result['wasRestored'])->toBeTrue()
        ->and($result['customer']->getKey())->toBe($customer->getKey())
        ->and($customer->fresh()->trashed())->toBeFalse();
});
