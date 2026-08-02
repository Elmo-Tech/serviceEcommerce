<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\OrderItemAnswer;
use App\Models\Service;
use App\Models\ServiceOrderField;
use App\Models\ServicePricingOption;
use App\Models\ServicePricingOptionValue;
use App\Services\Orders\OrderSnapshotFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds customer and address snapshots from current records or submitted address data', function () {
    $customer = Customer::factory()->create([
        'name' => 'Saved Customer',
        'phone' => '01012345678',
        'phone_normalized' => '01012345678',
        'email' => 'saved@example.com',
    ]);

    $address = CustomerAddress::factory()->create([
        'customer_id' => $customer->getKey(),
        'province' => 'Cairo',
        'city' => 'Nasr City',
        'address' => '15 Abbas El Akkad Street',
    ]);

    $factory = app(OrderSnapshotFactory::class);

    $customerSnapshot = $factory->customerSnapshot($customer, [
        'name' => 'Submitted Name',
        'phone' => '01012345678',
        'email' => 'submitted@example.com',
    ]);
    $storedAddressSnapshot = $factory->addressSnapshot($address);
    $newAddressSnapshot = $factory->addressSnapshot(null, [
        'province' => 'Giza',
        'city' => 'Dokki',
        'address' => 'Street 9',
    ]);

    expect($customerSnapshot)->toMatchArray([
        'customer_id' => $customer->getKey(),
        'customer_name' => 'Submitted Name',
        'customer_phone' => '01012345678',
        'customer_email' => 'submitted@example.com',
    ])->and($storedAddressSnapshot)->toMatchArray([
        'customer_address_id' => $address->getKey(),
        'address_province' => 'Cairo',
        'address_city' => 'Nasr City',
        'address_text' => '15 Abbas El Akkad Street',
    ])->and($newAddressSnapshot)->toMatchArray([
        'customer_address_id' => null,
        'address_province' => 'Giza',
        'address_city' => 'Dokki',
        'address_text' => 'Street 9',
    ]);
});

it('builds service option and answer snapshots', function () {
    $service = Service::factory()->create();
    $field = ServiceOrderField::factory()->for($service)->create();
    $option = ServicePricingOption::factory()->for($service)->create();
    $value = ServicePricingOptionValue::factory()->for($option, 'pricingOption')->create();
    $persistedAnswer = OrderItemAnswer::factory()->create();

    $factory = app(OrderSnapshotFactory::class);

    $serviceSnapshot = $factory->serviceSnapshot($service);
    $optionSnapshot = $factory->selectedOptionSnapshot($option, [$value]);
    $answerSnapshot = $factory->answerSnapshot($field, ' 200 x 100 cm ');
    $persistedSnapshot = $factory->persistedAnswerSnapshot($persistedAnswer);

    expect($serviceSnapshot['service_id'])->toBe($service->getKey())
        ->and($optionSnapshot['pricing_option_id'])->toBe($option->getKey())
        ->and($optionSnapshot['values'][0]['pricing_option_value_id'])->toBe($value->getKey())
        ->and($answerSnapshot['service_order_field_id'])->toBe($field->getKey())
        ->and($answerSnapshot['answer'])->toBe('200 x 100 cm')
        ->and($persistedSnapshot['orderItemAnswerId'])->toBe($persistedAnswer->getKey());
});
