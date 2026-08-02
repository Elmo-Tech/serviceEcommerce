<?php

declare(strict_types=1);

use App\Enums\Orders\DiscountType;
use App\Exceptions\ApiBusinessException;
use App\Models\Service;
use App\Models\ServicePricingOption;
use App\Models\ServicePricingOptionValue;
use App\Services\Orders\OrderPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calculates unit price subtotal discount and total for valid selections', function () {
    $service = Service::factory()->startFrom()->create([
        'base_price' => '500.00',
    ]);

    $option = ServicePricingOption::factory()->for($service)->create([
        'is_required' => true,
    ]);

    $value = ServicePricingOptionValue::factory()->for($option, 'pricingOption')->create([
        'price_adjustment' => '150.00',
    ]);

    $pricing = app(OrderPricingService::class);

    $result = $pricing->priceService($service, [[
        'pricingOptionId' => $option->getKey(),
        'valueIds' => [$value->getKey()],
    ]]);

    $itemTotal = $pricing->calculateItemTotal($result['unitPrice'], 2);
    $subtotal = $pricing->calculateSubtotal([$itemTotal, '100.00']);
    $discountAmount = $pricing->calculateDiscountAmount($subtotal, DiscountType::PERCENTAGE, '10.00');
    $total = $pricing->calculateTotal($subtotal, $discountAmount);

    expect($result['unitPrice'])->toBe('650.00')
        ->and($itemTotal)->toBe('1300.00')
        ->and($subtotal)->toBe('1400.00')
        ->and($discountAmount)->toBe('140.00')
        ->and($total)->toBe('1260.00');
});

it('rejects pricing selections for fixed-price services', function () {
    $service = Service::factory()->fixed()->create();
    $option = ServicePricingOption::factory()->for($service)->create();
    $value = ServicePricingOptionValue::factory()->for($option, 'pricingOption')->create();

    $pricing = app(OrderPricingService::class);

    expect(fn () => $pricing->priceService($service, [[
        'pricingOptionId' => $option->getKey(),
        'valueIds' => [$value->getKey()],
    ]]))->toThrow(ApiBusinessException::class, 'INVALID_PRICING_SELECTION');
});

it('rejects duplicate option ids duplicate value ids and missing required options', function () {
    $service = Service::factory()->startFrom()->create();
    $requiredOption = ServicePricingOption::factory()->for($service)->create(['is_required' => true]);
    $optionalOption = ServicePricingOption::factory()->for($service)->create(['is_required' => false]);
    $requiredValue = ServicePricingOptionValue::factory()->for($requiredOption, 'pricingOption')->create();
    $optionalValue = ServicePricingOptionValue::factory()->for($optionalOption, 'pricingOption')->create();

    $pricing = app(OrderPricingService::class);

    expect(fn () => $pricing->priceService($service, [
        ['pricingOptionId' => $requiredOption->getKey(), 'valueIds' => [$requiredValue->getKey()]],
        ['pricingOptionId' => $requiredOption->getKey(), 'valueIds' => [$requiredValue->getKey()]],
    ]))->toThrow(ApiBusinessException::class, 'INVALID_PRICING_SELECTION');

    expect(fn () => $pricing->priceService($service, [
        ['pricingOptionId' => $optionalOption->getKey(), 'valueIds' => [$optionalValue->getKey(), $optionalValue->getKey()]],
    ]))->toThrow(ApiBusinessException::class, 'INVALID_PRICING_SELECTION');

    expect(fn () => $pricing->priceService($service, [
        ['pricingOptionId' => $optionalOption->getKey(), 'valueIds' => [$optionalValue->getKey()]],
    ]))->toThrow(ApiBusinessException::class, 'INVALID_PRICING_SELECTION');
});
