<?php

declare(strict_types=1);

use App\Enums\Orders\OrderStatus;
use App\Exceptions\ApiBusinessException;
use App\Services\Orders\OrderStatusTransitionService;

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
