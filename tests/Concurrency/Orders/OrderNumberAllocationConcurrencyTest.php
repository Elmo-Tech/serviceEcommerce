<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderNumberSequence;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    resetOrderConcurrencyDatabase();
});

afterEach(function (): void {
    RefreshDatabaseState::$migrated = false;
});

function startOrderConcurrencyProcess(string ...$arguments): Process
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

function waitForOrderConcurrencyProcess(Process $process): array
{
    $process->wait();

    if (! $process->isSuccessful()) {
        throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Order concurrency runner failed.');
    }

    return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
}

function resetOrderConcurrencyDatabase(): void
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

it('keeps concurrent order-number allocation unique, monotonic, and bounded at 9999 without partial extra orders', function () {
    $timestamp = CarbonImmutable::create(2026, 8, 2, 10, 0, 0, 'UTC')->toIso8601String();

    $processes = collect(range(1, 12))
        ->map(fn () => startOrderConcurrencyProcess('allocate-and-create-order', $timestamp))
        ->all();

    $results = array_map(
        static fn (Process $process): array => waitForOrderConcurrencyProcess($process),
        $processes,
    );

    $successes = collect($results)->where('status', 'success')->values();
    $orderNumbers = $successes->pluck('orderNumber')->all();

    $storedOrderNumbers = Order::query()
        ->orderBy('id')
        ->pluck('order_number')
        ->all();

    $numericSuffixes = collect($storedOrderNumbers)
        ->map(static fn (string $orderNumber): int => (int) substr($orderNumber, (int) strrpos($orderNumber, '-') + 1))
        ->sort()
        ->values()
        ->all();

    expect($successes)->toHaveCount(12)
        ->and($orderNumbers)->toHaveCount(12)
        ->and(array_values(array_unique($orderNumbers)))->toHaveCount(12)
        ->and(collect($storedOrderNumbers)->sort()->values()->all())->toBe(collect($orderNumbers)->sort()->values()->all())
        ->and($numericSuffixes)->toBe(range(1, 12))
        ->and(OrderNumberSequence::query()->where('business_date', '2026-08-02')->value('last_sequence'))->toBe(12);

    resetOrderConcurrencyDatabase();

    $timestamp = CarbonImmutable::create(2026, 8, 2, 11, 0, 0, 'UTC');

    OrderNumberSequence::query()->updateOrCreate(
        ['business_date' => $timestamp->toDateString()],
        ['last_sequence' => 9998],
    );

    $firstProcess = startOrderConcurrencyProcess('allocate-and-create-order', $timestamp->toIso8601String());
    $secondProcess = startOrderConcurrencyProcess('allocate-and-create-order', $timestamp->toIso8601String());

    $results = [
        waitForOrderConcurrencyProcess($firstProcess),
        waitForOrderConcurrencyProcess($secondProcess),
    ];

    $successes = collect($results)->where('status', 'success')->values();
    $businessErrors = collect($results)->where('status', 'business_error')->values();

    $storedOrderNumbers = Order::query()->pluck('order_number')->all();
    $storedSuffixes = collect($storedOrderNumbers)
        ->map(static fn (string $orderNumber): int => (int) substr($orderNumber, (int) strrpos($orderNumber, '-') + 1))
        ->sort()
        ->values()
        ->all();

    expect($successes)->toHaveCount(1)
        ->and($businessErrors)->toHaveCount(1)
        ->and($businessErrors->pluck('code')->all())->toBe(['ORDER_NUMBER_SEQUENCE_EXHAUSTED'])
        ->and($storedOrderNumbers)->toHaveCount(1)
        ->and($storedSuffixes)->toBe([9999])
        ->and(OrderNumberSequence::query()->where('business_date', $timestamp->toDateString())->value('last_sequence'))->toBe(9999);
});
