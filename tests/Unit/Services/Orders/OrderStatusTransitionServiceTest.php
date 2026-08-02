<?php

declare(strict_types=1);

use App\Enums\Orders\OrderStatus;
use App\Exceptions\ApiBusinessException;
use App\Services\Orders\OrderStatusTransitionService;
use Illuminate\Support\Carbon;

it('returns the approved available transitions for each order status', function () {
    $service = app(OrderStatusTransitionService::class);

    expect($service->availableTransitions(OrderStatus::PENDING))
        ->toBe([OrderStatus::CONFIRMED, OrderStatus::CANCELLED])
        ->and($service->availableTransitions(OrderStatus::CONFIRMED))
        ->toBe([OrderStatus::IN_PROGRESS, OrderStatus::CANCELLED])
        ->and($service->availableTransitions(OrderStatus::IN_PROGRESS))
        ->toBe([OrderStatus::COMPLETED, OrderStatus::CANCELLED])
        ->and($service->availableTransitions(OrderStatus::COMPLETED))
        ->toBe([OrderStatus::CANCELLED])
        ->and($service->availableTransitions(OrderStatus::CANCELLED))
        ->toBe([]);
});

it('allows approved transitions and rejects invalid ones', function () {
    $service = app(OrderStatusTransitionService::class);

    $service->ensureTransitionAllowed(OrderStatus::PENDING, OrderStatus::CONFIRMED);
    $service->ensureTransitionAllowed(OrderStatus::COMPLETED, OrderStatus::CANCELLED, 'Customer requested cancellation');

    expect(fn () => $service->ensureTransitionAllowed(OrderStatus::PENDING, OrderStatus::IN_PROGRESS))
        ->toThrow(ApiBusinessException::class, 'INVALID_ORDER_STATUS_TRANSITION');
});

it('requires a reason when cancelling', function () {
    $service = app(OrderStatusTransitionService::class);

    expect(fn () => $service->ensureTransitionAllowed(OrderStatus::CONFIRMED, OrderStatus::CANCELLED))
        ->toThrow(ApiBusinessException::class, 'CANCELLATION_REASON_REQUIRED');
});

it('sets completed_at only on the first completion and preserves an existing value', function () {
    $service = app(OrderStatusTransitionService::class);

    Carbon::setTestNow(Carbon::parse('2026-08-02 09:30:00', 'UTC'));

    $firstCompletion = $service->resolveCompletedAt(
        OrderStatus::IN_PROGRESS,
        OrderStatus::COMPLETED,
        null,
    );

    $existingCompletion = Carbon::parse('2026-08-02 08:00:00', 'UTC');

    expect($firstCompletion?->toJSON())->toBe('2026-08-02T09:30:00.000000Z')
        ->and($service->resolveCompletedAt(
            OrderStatus::COMPLETED,
            OrderStatus::CANCELLED,
            $existingCompletion,
        )?->toJSON())->toBe('2026-08-02T08:00:00.000000Z')
        ->and($service->resolveCompletedAt(
            OrderStatus::PENDING,
            OrderStatus::CONFIRMED,
            null,
        ))->toBeNull();

    Carbon::setTestNow();
});
