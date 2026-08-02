<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\HttpStatusCode;
use App\Enums\Orders\OrderStatus;
use App\Enums\Orders\PaymentStatus;
use App\Exceptions\ApiBusinessException;
use App\Models\Order;
use App\Services\Orders\OrderAttachmentStore;
use Illuminate\Support\Facades\DB;

class DeleteOrderAction
{
    public function __construct(
        private readonly OrderAttachmentStore $orderAttachmentStore,
    ) {}

    public function execute(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->with('items.attachments')
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedOrder->created_by_admin_id === null
                || $lockedOrder->status !== OrderStatus::PENDING
                || $lockedOrder->payment_status !== PaymentStatus::UNPAID
                || (float) $lockedOrder->paid_amount !== 0.0
            ) {
                throw new ApiBusinessException(
                    'orders.errors.order_delete_not_allowed',
                    'ORDER_DELETE_NOT_ALLOWED',
                    HttpStatusCode::CONFLICT,
                );
            }

            foreach ($lockedOrder->items as $item) {
                foreach ($item->attachments as $attachment) {
                    $this->orderAttachmentStore->deleteStoredFile($attachment);
                }
            }

            $lockedOrder->delete();
        });
    }
}
