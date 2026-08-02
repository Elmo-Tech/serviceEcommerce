<?php

declare(strict_types=1);

use App\Enums\Orders\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    foreach ([
        [PHP_BINARY, base_path('artisan'), 'db:wipe', '--force'],
        [PHP_BINARY, base_path('artisan'), 'migrate', '--force'],
    ] as $command) {
        $process = new Process($command, base_path());
        $process->setTimeout(120);
        $process->mustRun();
    }
});

afterEach(function (): void {
    RefreshDatabaseState::$migrated = false;
});

function startCompletedAtTransitionProcess(int $orderId): Process
{
    $process = new Process([
        PHP_BINARY,
        base_path('tests/Support/OrderConcurrencyRunner.php'),
        'change-order-status',
        (string) $orderId,
        (string) OrderStatus::COMPLETED->value,
    ]);
    $process->setTimeout(20);
    $process->start();

    return $process;
}

function completedAtTransitionResult(Process $process): array
{
    $process->wait();

    if (! $process->isSuccessful()) {
        throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Completion concurrency runner failed.');
    }

    return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
}

it('serializes concurrent completion attempts and persists one authoritative timestamp', function (): void {
    $order = Order::factory()->create([
        'status' => OrderStatus::IN_PROGRESS,
        'completed_at' => null,
    ]);

    $first = startCompletedAtTransitionProcess((int) $order->getKey());
    $second = startCompletedAtTransitionProcess((int) $order->getKey());
    $results = [completedAtTransitionResult($first), completedAtTransitionResult($second)];

    $order->refresh();

    expect(collect($results)->where('status', 'success'))->toHaveCount(1)
        ->and(collect($results)->where('status', 'business_error'))->toHaveCount(1)
        ->and($order->status)->toBe(OrderStatus::COMPLETED)
        ->and($order->completed_at)->not->toBeNull()
        ->and(collect($results)->where('status', 'success')->pluck('completedAt')->filter())->toHaveCount(1);
});
