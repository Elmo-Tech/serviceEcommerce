<?php

declare(strict_types=1);

use App\Enums\Orders\OrderPlace;
use App\Enums\Orders\OrderStatus;
use App\Enums\Orders\PaymentStatus;
use App\Enums\Services\ServicePriceType;
use App\Enums\Services\ServicePricingInputType;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    resetFinancialConcurrencyDatabase();
});

afterEach(function (): void {
    RefreshDatabaseState::$migrated = false;
});

function startFinancialRecalculationConcurrencyProcess(?string ...$arguments): Process
{
    $arguments = array_values(array_filter(
        $arguments,
        static fn (?string $argument): bool => $argument !== null,
    ));

    $process = new Process([
        PHP_BINARY,
        base_path('tests/Support/OrderConcurrencyRunner.php'),
        ...$arguments,
    ]);

    $process->setTimeout(20);
    $process->start();

    return $process;
}

function waitForFinancialRecalculationConcurrencyProcess(Process $process): array
{
    $process->wait();

    if (! $process->isSuccessful()) {
        throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Financial recalculation concurrency runner failed.');
    }

    return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
}

function resetFinancialConcurrencyDatabase(): void
{
    foreach ([
        [PHP_BINARY, base_path('artisan'), 'db:wipe', '--force'],
        [PHP_BINARY, base_path('artisan'), 'migrate', '--force'],
    ] as $command) {
        $process = new Process($command, base_path());
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'Financial concurrency database reset failed.');
        }
    }
}

it('keeps concurrent payment, quantity, selected-option, discount, and status mutations internally consistent', function () {
    $category = Category::factory()->root()->create(['is_active' => true]);
    $subcategory = Category::factory()->subcategory($category)->create(['is_active' => true]);
    $service = Service::factory()->underSubcategory($category, $subcategory)->create([
        'is_active' => true,
        'is_available' => true,
        'price_type' => ServicePriceType::START_FROM,
        'base_price' => '100.00',
    ]);

    $pricingOption = $service->pricingOptions()->create([
        'name_ar' => 'الحجم',
        'name_en' => 'Size',
        'option_type' => \App\Enums\Services\ServicePricingOptionType::ADD_ON,
        'input_type' => ServicePricingInputType::SELECT,
        'is_required' => true,
        'sort_order' => 1,
    ]);

    $baseValue = $pricingOption->values()->create([
        'label_ar' => 'صغير',
        'label_en' => 'Small',
        'price_adjustment' => '0.00',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $premiumValue = $pricingOption->values()->create([
        'label_ar' => 'كبير',
        'label_en' => 'Large',
        'price_adjustment' => '50.00',
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $order = Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'order_place' => OrderPlace::WEBSITE,
        'payment_status' => PaymentStatus::UNPAID,
        'subtotal' => '100.00',
        'total' => '100.00',
        'paid_amount' => '0.00',
        'discount_amount' => '0.00',
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->getKey(),
        'service_id' => $service->getKey(),
        'service_name_ar' => $service->name_ar,
        'service_name_en' => $service->name_en,
        'service_slug_ar' => $service->slug_ar,
        'service_slug_en' => $service->slug_en,
        'price_type' => $service->price_type,
        'base_price' => '100.00',
        'unit_price' => '100.00',
        'quantity' => 1,
        'item_total' => '100.00',
    ]);

    $item->selectedOptions()->create([
        'pricing_option_id' => $pricingOption->getKey(),
        'option_name_ar' => $pricingOption->name_ar,
        'option_name_en' => $pricingOption->name_en,
        'input_type' => $pricingOption->input_type->value,
        'is_required' => true,
    ])->values()->create([
        'pricing_option_value_id' => $baseValue->getKey(),
        'value_label_ar' => $baseValue->label_ar,
        'value_label_en' => $baseValue->label_en,
        'price_adjustment' => $baseValue->price_adjustment,
    ]);

    $processes = [
        startFinancialRecalculationConcurrencyProcess('update-order-payment', (string) $order->getKey(), '75.00'),
        startFinancialRecalculationConcurrencyProcess('update-order-item', (string) $order->getKey(), (string) $item->getKey(), json_encode([
            'quantity' => 2,
        ], JSON_THROW_ON_ERROR)),
        startFinancialRecalculationConcurrencyProcess('update-order-item', (string) $order->getKey(), (string) $item->getKey(), json_encode([
            'selectedOptions' => [
                [
                    'pricingOptionId' => $pricingOption->getKey(),
                    'valueIds' => [$premiumValue->getKey()],
                ],
            ],
        ], JSON_THROW_ON_ERROR)),
        startFinancialRecalculationConcurrencyProcess('update-order-discount', (string) $order->getKey(), '0', '20.00', 'Concurrent discount'),
        startFinancialRecalculationConcurrencyProcess('change-order-status', (string) $order->getKey(), (string) OrderStatus::CONFIRMED->value, null),
    ];

    $results = array_map(
        static fn (Process $process): array => waitForFinancialRecalculationConcurrencyProcess($process),
        $processes,
    );

    expect(collect($results)->where('status', 'success'))->toHaveCount(5);

    $freshOrder = Order::query()->with('items')->findOrFail($order->getKey());
    $freshItem = $freshOrder->items->sole();

    $expectedSubtotal = number_format(((float) $freshItem->item_total), 2, '.', '');
    $expectedTotal = number_format((float) $expectedSubtotal - (float) $freshOrder->discount_amount, 2, '.', '');
    $expectedRemaining = number_format((float) $expectedTotal - (float) $freshOrder->paid_amount, 2, '.', '');

    expect(number_format((float) $freshOrder->subtotal, 2, '.', ''))->toBe($expectedSubtotal)
        ->and(number_format((float) $freshOrder->total, 2, '.', ''))->toBe($expectedTotal)
        ->and(number_format((float) ($freshOrder->paid_amount - $freshOrder->total), 2, '.', ''))->toBe(number_format((float) $freshOrder->paid_amount - (float) $freshOrder->total, 2, '.', ''))
        ->and($freshOrder->status)->toBe(OrderStatus::CONFIRMED)
        ->and(number_format((float) $freshOrder->total - (float) $freshOrder->paid_amount, 2, '.', ''))->toBe($expectedRemaining)
        ->and(number_format((float) $freshItem->unit_price * (int) $freshItem->quantity, 2, '.', ''))->toBe(number_format((float) $freshItem->item_total, 2, '.', ''));
});
