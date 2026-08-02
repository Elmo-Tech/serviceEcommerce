<?php

declare(strict_types=1);

use App\Enums\Orders\OrderStatus;
use App\Enums\Orders\PaymentStatus;
use App\Enums\Services\ServicePriceType;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    resetFinalItemDeletionConcurrencyDatabase();
});

afterEach(function (): void {
    RefreshDatabaseState::$migrated = false;
});

function startFinalItemDeletionConcurrencyProcess(string ...$arguments): Process
{
    $process = new Process([
        PHP_BINARY,
        base_path('tests/Support/OrderConcurrencyRunner.php'),
        ...$arguments,
    ]);

    $process->setTimeout(20);
    $process->start();

    return $process;
}

function waitForFinalItemDeletionConcurrencyProcess(Process $process): array
{
    $process->wait();

    if (! $process->isSuccessful()) {
        throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Final-item deletion concurrency runner failed.');
    }

    return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
}

function resetFinalItemDeletionConcurrencyDatabase(): void
{
    foreach ([
        [PHP_BINARY, base_path('artisan'), 'db:wipe', '--force'],
        [PHP_BINARY, base_path('artisan'), 'migrate', '--force'],
    ] as $command) {
        $process = new Process($command, base_path());
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'Order concurrency database reset failed.');
        }
    }
}

it('prevents two concurrent deletions from removing the final item', function () {
    $category = Category::factory()->root()->create(['is_active' => true]);
    $subcategory = Category::factory()->subcategory($category)->create(['is_active' => true]);
    $service = Service::factory()->underSubcategory($category, $subcategory)->create([
        'is_active' => true,
        'is_available' => true,
        'price_type' => ServicePriceType::FIXED,
        'base_price' => '75.00',
    ]);

    $order = Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::UNPAID,
    ]);

    $firstItem = OrderItem::factory()->create([
        'order_id' => $order->getKey(),
        'service_id' => $service->getKey(),
        'service_name_ar' => $service->name_ar,
        'service_name_en' => $service->name_en,
        'service_slug_ar' => $service->slug_ar,
        'service_slug_en' => $service->slug_en,
        'price_type' => $service->price_type,
        'base_price' => '75.00',
        'unit_price' => '75.00',
        'quantity' => 1,
        'item_total' => '75.00',
    ]);
    $secondItem = OrderItem::factory()->create([
        'order_id' => $order->getKey(),
        'service_id' => $service->getKey(),
        'service_name_ar' => $service->name_ar,
        'service_name_en' => $service->name_en,
        'service_slug_ar' => $service->slug_ar,
        'service_slug_en' => $service->slug_en,
        'price_type' => $service->price_type,
        'base_price' => '75.00',
        'unit_price' => '75.00',
        'quantity' => 1,
        'item_total' => '75.00',
    ]);

    $firstProcess = startFinalItemDeletionConcurrencyProcess('delete-order-item', (string) $order->getKey(), (string) $firstItem->getKey());
    $secondProcess = startFinalItemDeletionConcurrencyProcess('delete-order-item', (string) $order->getKey(), (string) $secondItem->getKey());

    $results = [
        waitForFinalItemDeletionConcurrencyProcess($firstProcess),
        waitForFinalItemDeletionConcurrencyProcess($secondProcess),
    ];

    $successes = collect($results)->where('status', 'success')->values();
    $businessErrors = collect($results)->where('status', 'business_error')->values();

    expect($successes)->toHaveCount(1)
        ->and($businessErrors)->toHaveCount(1)
        ->and($businessErrors->pluck('code')->all())->toBe(['ORDER_REQUIRES_AT_LEAST_ONE_ITEM'])
        ->and(Order::query()->whereKey($order->getKey())->withCount('items')->sole()->items_count)->toBe(1);
});
