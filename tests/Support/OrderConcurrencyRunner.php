<?php

declare(strict_types=1);

use App\Enums\Orders\OrderPlace;
use App\Enums\Orders\OrderStatus;
use App\Enums\Orders\PaymentStatus;
use App\Actions\Orders\CreatePublicOrderAction;
use App\Exceptions\ApiBusinessException;
use App\Models\Order;
use App\Models\OrderNumberSequence;
use App\Services\Orders\OrderNumberAllocator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$mode = $argv[1] ?? null;

if (! is_string($mode) || $mode === '') {
    fwrite(STDERR, 'Missing concurrency mode.');

    exit(1);
}

function orderConcurrencyTimestamp(?string $raw): ?Carbon
{
    if (! is_string($raw) || $raw === '') {
        return null;
    }

    return Carbon::parse($raw);
}

function createMinimalConcurrentOrder(OrderNumberAllocator $allocator, ?Carbon $timestamp = null): Order
{
    return DB::transaction(function () use ($allocator, $timestamp): Order {
        $orderNumber = $allocator->allocate($timestamp);

        return Order::query()->create([
            'order_number' => $orderNumber,
            'customer_name' => 'Concurrency Test Customer',
            'customer_phone' => '01000000000',
            'status' => OrderStatus::PENDING,
            'order_place' => OrderPlace::WEBSITE,
            'subtotal' => '100.00',
            'discount_type' => null,
            'discount_value' => '0.00',
            'discount_amount' => '0.00',
            'total' => '100.00',
            'payment_status' => PaymentStatus::UNPAID,
            'paid_amount' => '0.00',
        ]);
    }, 5);
}

try {
    $allocator = $app->make(OrderNumberAllocator::class);

    switch ($mode) {
        case 'allocate-and-create-order':
            $timestamp = orderConcurrencyTimestamp($argv[2] ?? null);
            $order = createMinimalConcurrentOrder($allocator, $timestamp);

            echo json_encode([
                'status' => 'success',
                'orderId' => $order->getKey(),
                'orderNumber' => $order->order_number,
            ], JSON_THROW_ON_ERROR);

            exit(0);

        case 'seed-sequence':
            $timestamp = orderConcurrencyTimestamp($argv[2] ?? null) ?? now()->utc();
            $lastSequence = (int) ($argv[3] ?? 0);

            OrderNumberSequence::query()->updateOrCreate(
                ['business_date' => $timestamp->toDateString()],
                ['last_sequence' => $lastSequence],
            );

            echo json_encode([
                'status' => 'success',
                'businessDate' => $timestamp->toDateString(),
                'lastSequence' => $lastSequence,
            ], JSON_THROW_ON_ERROR);

            exit(0);

        case 'create-public-order':
            $idempotencyKey = (string) ($argv[2] ?? '');
            $payload = json_decode($argv[3] ?? '', true, 512, JSON_THROW_ON_ERROR);
            $result = $app->make(CreatePublicOrderAction::class)->execute($idempotencyKey, $payload);

            echo json_encode([
                'status' => 'success',
                'orderId' => $result['order']->getKey(),
                'orderNumber' => $result['order']->order_number,
                'isReplay' => $result['isReplay'],
            ], JSON_THROW_ON_ERROR);

            exit(0);
    }

    fwrite(STDERR, 'Unknown concurrency mode.');

    exit(1);
} catch (ApiBusinessException $exception) {
    echo json_encode([
        'status' => 'business_error',
        'code' => $exception->machineCode(),
        'httpStatus' => $exception->status()->value,
    ], JSON_THROW_ON_ERROR);

    exit(0);
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable::class.': '.$throwable->getMessage());

    exit(1);
}
