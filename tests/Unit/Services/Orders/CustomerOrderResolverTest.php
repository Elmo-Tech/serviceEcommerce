<?php

declare(strict_types=1);

use App\Exceptions\ApiBusinessException;
use App\Models\Customer;
use App\Services\Orders\CustomerOrderResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('normalizes supported egyptian phone formats to local mobile shape', function () {
    $service = app(CustomerOrderResolver::class);

    expect($service->normalizeEgyptianPhone('010 1234 5678'))->toBe('01012345678')
        ->and($service->normalizeEgyptianPhone('+20 101 234 5678'))->toBe('01012345678')
        ->and($service->normalizeEgyptianPhone('00201012345678'))->toBe('01012345678')
        ->and($service->normalizeEgyptianPhone('201012345678'))->toBe('01012345678')
        ->and($service->normalizeEgyptianPhone('123'))->toBeNull();
});

it('reuses a single matching public customer without overwriting saved data', function () {
    $customer = Customer::factory()->create([
        'name' => 'Saved Name',
        'email' => 'saved@example.com',
        'phone' => '01012345678',
        'phone_normalized' => '01012345678',
    ]);

    $service = app(CustomerOrderResolver::class);

    $resolved = $service->resolvePublicCustomer([
        'name' => 'Submitted Name',
        'email' => 'submitted@example.com',
        'phone' => '01012345678',
    ]);

    expect($resolved->is($customer))->toBeTrue()
        ->and($resolved->fresh()?->name)->toBe('Saved Name')
        ->and($resolved->fresh()?->email)->toBe('saved@example.com');
});

it('uses email to disambiguate duplicated phone matches when exactly one email matches', function () {
    Customer::factory()->create([
        'email' => 'other@example.com',
        'phone' => '01012345678',
        'phone_normalized' => '01012345678',
    ]);

    $expected = Customer::factory()->create([
        'email' => 'chosen@example.com',
        'phone' => '01012345678',
        'phone_normalized' => '01012345678',
    ]);

    $service = app(CustomerOrderResolver::class);

    $resolved = $service->resolvePublicCustomer([
        'name' => 'Guest',
        'email' => 'chosen@example.com',
        'phone' => '01012345678',
    ]);

    expect($resolved->is($expected))->toBeTrue();
});

it('creates a new public customer when duplicated phone matches stay ambiguous', function () {
    Customer::factory()->create([
        'email' => 'first@example.com',
        'phone' => '01012345678',
        'phone_normalized' => '01012345678',
    ]);

    Customer::factory()->create([
        'email' => 'second@example.com',
        'phone' => '01012345678',
        'phone_normalized' => '01012345678',
    ]);

    $service = app(CustomerOrderResolver::class);

    $created = $service->resolvePublicCustomer([
        'name' => 'Guest',
        'email' => 'new@example.com',
        'phone' => '01012345678',
    ]);

    expect($created->name)->toBe('Guest')
        ->and($created->email)->toBe('new@example.com')
        ->and(Customer::query()->where('phone_normalized', '01012345678')->count())->toBe(3);
});

it('enforces exactly one admin customer source', function () {
    $service = app(CustomerOrderResolver::class);

    expect(fn () => $service->resolveAdminCustomer(null, null))
        ->toThrow(ApiBusinessException::class, 'CUSTOMER_SOURCE_INVALID');
});
