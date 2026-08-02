<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Orders\OrderAttachmentStore;
use App\Services\Orders\OrderPricingService;
use Illuminate\Support\Facades\DB;

class DeleteOrderItemAction
{
    public function __construct(
        private readonly OrderPricingService $orderPricingService,
        private readonly OrderAttachmentStore $orderAttachmentStore,
    ) {}

    public function execute(Order $order, OrderItem $orderItem): void
    {
        DB::transaction(function () use ($order, $orderItem): void {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedOrder->isEditable()) {
                throw new ApiBusinessException(
                    'orders.errors.order_not_editable',
                    'ORDER_NOT_EDITABLE',
                    HttpStatusCode::CONFLICT,
                );
            }

            $lockedItem = $lockedOrder->items()
                ->whereKey($orderItem->getKey())
                ->with('attachments')
                ->firstOrFail();

            if ($lockedOrder->items()->count() <= 1) {
                throw new ApiBusinessException(
                    'orders.errors.order_requires_at_least_one_item',
                    'ORDER_REQUIRES_AT_LEAST_ONE_ITEM',
                    HttpStatusCode::CONFLICT,
                );
            }

            foreach ($lockedItem->attachments as $attachment) {
                $this->orderAttachmentStore->deleteStoredFile($attachment);
            }

            $lockedItem->delete();

            $itemTotals = $lockedOrder->items()
                ->whereKeyNot($lockedItem->getKey())
                ->pluck('item_total')
                ->all();

            $subtotal = $this->orderPricingService->calculateSubtotal($itemTotals);
            $total = $this->orderPricingService->calculateTotal($subtotal, (string) $lockedOrder->discount_amount);

            $lockedOrder->forceFill([
                'subtotal' => $subtotal,
                'total' => $total,
            ])->save();
        });
    }
}
