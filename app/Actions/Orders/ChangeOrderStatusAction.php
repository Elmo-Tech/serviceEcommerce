<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\Orders\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderStatusTransitionService;
use Illuminate\Support\Facades\DB;

class ChangeOrderStatusAction
{
    public function __construct(
        private readonly OrderStatusTransitionService $orderStatusTransitionService,
    ) {}

    public function execute(Order $order, array $payload, User $admin): Order
    {
        return DB::transaction(function () use ($order, $payload, $admin): Order {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $targetStatus = OrderStatus::from((int) $payload['status']);
            $reason = array_key_exists('reason', $payload) ? $payload['reason'] : null;

            $this->orderStatusTransitionService->ensureTransitionAllowed($lockedOrder->status, $targetStatus, $reason);

            $completedAt = $this->orderStatusTransitionService->resolveCompletedAt(
                $lockedOrder->status,
                $targetStatus,
                $lockedOrder->completed_at,
            );

            $lockedOrder->forceFill([
                'status' => $targetStatus,
                'cancellation_reason' => $targetStatus === OrderStatus::CANCELLED ? $reason : null,
                'cancelled_at' => $targetStatus === OrderStatus::CANCELLED ? now() : null,
                'completed_at' => $completedAt,
                'cancelled_by_admin_id' => $targetStatus === OrderStatus::CANCELLED ? $admin->getKey() : null,
            ])->save();

            return $lockedOrder->fresh([
                'cancelledByAdmin',
                'createdByAdmin',
                'items.selectedOptions.values',
                'items.answers',
                'items.attachments',
            ]) ?? $lockedOrder;
        });
    }
}
