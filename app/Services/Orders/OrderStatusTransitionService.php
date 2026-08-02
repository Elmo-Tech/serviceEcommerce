<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enums\HttpStatusCode;
use App\Enums\Orders\OrderStatus;
use App\Exceptions\ApiBusinessException;

class OrderStatusTransitionService
{
    /**
     * @return array<int, OrderStatus>
     */
    public function availableTransitions(OrderStatus $currentStatus): array
    {
        return match ($currentStatus) {
            OrderStatus::PENDING => [OrderStatus::CONFIRMED, OrderStatus::CANCELLED],
            OrderStatus::CONFIRMED => [OrderStatus::IN_PROGRESS, OrderStatus::CANCELLED],
            OrderStatus::IN_PROGRESS => [OrderStatus::COMPLETED, OrderStatus::CANCELLED],
            OrderStatus::COMPLETED => [OrderStatus::CANCELLED],
            OrderStatus::CANCELLED => [],
        };
    }

    public function ensureTransitionAllowed(
        OrderStatus $currentStatus,
        OrderStatus $targetStatus,
        ?string $reason = null,
    ): void {
        if ($targetStatus === OrderStatus::CANCELLED && $this->normalizeReason($reason) === null) {
            throw new ApiBusinessException(
                'orders.errors.cancellation_reason_required',
                'CANCELLATION_REASON_REQUIRED',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }

        if (! in_array($targetStatus, $this->availableTransitions($currentStatus), true)) {
            throw new ApiBusinessException(
                'orders.errors.invalid_status_transition',
                'INVALID_ORDER_STATUS_TRANSITION',
                HttpStatusCode::CONFLICT,
            );
        }
    }

    private function normalizeReason(?string $reason): ?string
    {
        $normalized = is_string($reason) ? trim($reason) : '';

        return $normalized === '' ? null : $normalized;
    }
}
