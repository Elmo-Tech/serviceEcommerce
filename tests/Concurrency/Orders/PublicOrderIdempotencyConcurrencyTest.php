<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderIdempotencyKey;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    resetPublicOrderConcurrencyDatabase();
});

afterEach(function (): void {
    RefreshDatabaseState::$migrated = false;
});

function startPublicOrderConcurrencyProcess(string ...$arguments): Process
{
    $process = new Process([
        PHP_BINARY,
        base_path('tests/Support/OrderConcurrencyRunner.php'),
        ...$arguments,
    ]);

    $process->setTimeout(30);
    $process->start();

    return $process;
}

function waitForPublicOrderConcurrencyProcess(Process $process): array
{
    $process->wait();

    if (! $process->isSuccessful()) {
        throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Public order concurrency runner failed.');
    }

    return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
}

function resetPublicOrderConcurrencyDatabase(): void
{
    foreach ([
        [PHP_BINARY, base_path('artisan'), 'db:wipe', '--force'],
        [PHP_BINARY, base_path('artisan'), 'migrate', '--force'],
    ] as $command) {
        $process = new Process($command, base_path());
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'Public order concurrency database reset failed.');
        }
    }
}

function publicOrderConcurrencyPayload(int $serviceId, array $overrides = []): array
{
    return array_replace_recursive([
        'customer' => [
            'name' => 'Concurrent Public Customer',
            'email' => 'public.concurrent@example.com',
            'phone' => '01001234567',
        ],
        'items' => [
            [
                'serviceId' => $serviceId,
                'quantity' => 1,
            ],
        ],
    ], $overrides);
}

it('keeps public idempotency concurrency consistent across identical, conflicting, and failed create races', function () {
    $category = Category::factory()->root()->create(['is_active' => true]);
    $subcategory = Category::factory()->subcategory($category)->create(['is_active' => true]);
    $service = Service::factory()->underSubcategory($category, $subcategory)->create([
        'is_active' => true,
        'is_available' => true,
    ]);

    $idempotencyKey = (string) Str::uuid();
    $payload = json_encode(publicOrderConcurrencyPayload($service->getKey()), JSON_THROW_ON_ERROR);

    $firstProcess = startPublicOrderConcurrencyProcess('create-public-order', $idempotencyKey, $payload);
    $secondProcess = startPublicOrderConcurrencyProcess('create-public-order', $idempotencyKey, $payload);

    $results = [
        waitForPublicOrderConcurrencyProcess($firstProcess),
        waitForPublicOrderConcurrencyProcess($secondProcess),
    ];

    $successes = collect($results)->where('status', 'success')->values();
    $uniqueOrderIds = $successes->pluck('orderId')->unique()->values()->all();
    $replayFlags = $successes->pluck('isReplay')->sort()->values()->all();

    $reservation = OrderIdempotencyKey::query()->sole();

    expect($successes)->toHaveCount(2)
        ->and($uniqueOrderIds)->toHaveCount(1)
        ->and($replayFlags)->toBe([false, true])
        ->and(Order::query()->count())->toBe(1)
        ->and($reservation->order_id)->not->toBeNull()
        ->and($reservation->completed_at)->not->toBeNull();

    $idempotencyKey = (string) Str::uuid();

    $firstPayload = json_encode(publicOrderConcurrencyPayload($service->getKey()), JSON_THROW_ON_ERROR);
    $secondPayload = json_encode(publicOrderConcurrencyPayload($service->getKey(), [
        'customer' => [
            'name' => 'Conflicting Payload Customer',
        ],
    ]), JSON_THROW_ON_ERROR);

    $firstProcess = startPublicOrderConcurrencyProcess('create-public-order', $idempotencyKey, $firstPayload);
    $secondProcess = startPublicOrderConcurrencyProcess('create-public-order', $idempotencyKey, $secondPayload);

    $results = [
        waitForPublicOrderConcurrencyProcess($firstProcess),
        waitForPublicOrderConcurrencyProcess($secondProcess),
    ];

    expect(collect($results)->where('status', 'success'))->toHaveCount(1)
        ->and(collect($results)->where('status', 'business_error'))->toHaveCount(1)
        ->and(collect($results)->where('status', 'business_error')->pluck('code')->all())->toBe(['IDEMPOTENCY_KEY_REUSED'])
        ->and(Order::query()->count())->toBe(2)
        ->and(OrderIdempotencyKey::query()->count())->toBe(2);

    $idempotencyKey = (string) Str::uuid();
    $payload = json_encode(publicOrderConcurrencyPayload(999999), JSON_THROW_ON_ERROR);

    $firstProcess = startPublicOrderConcurrencyProcess('create-public-order', $idempotencyKey, $payload);
    $secondProcess = startPublicOrderConcurrencyProcess('create-public-order', $idempotencyKey, $payload);

    $results = [
        waitForPublicOrderConcurrencyProcess($firstProcess),
        waitForPublicOrderConcurrencyProcess($secondProcess),
    ];

    expect(collect($results)->pluck('status')->unique()->all())->toBe(['business_error'])
        ->and(collect($results)->pluck('code')->unique()->all())->toBe(['SERVICE_NOT_FOUND'])
        ->and(Order::query()->count())->toBe(2)
        ->and(OrderIdempotencyKey::query()->count())->toBe(2);
});
