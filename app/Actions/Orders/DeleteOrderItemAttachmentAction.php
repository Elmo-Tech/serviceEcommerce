<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Order;
use App\Models\OrderItemAttachment;
use App\Services\Orders\OrderAttachmentStore;
use Illuminate\Support\Facades\DB;

class DeleteOrderItemAttachmentAction
{
    public function __construct(
        private readonly OrderAttachmentStore $orderAttachmentStore,
    ) {}

    public function execute(Order $order, OrderItemAttachment $attachment): void
    {
        DB::transaction(function () use ($order, $attachment): void {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! $lockedOrder->isEditable()) {
                throw new ApiBusinessException(
                    'orders.errors.order_not_editable',
                    'ORDER_NOT_EDITABLE',
                    HttpStatusCode::CONFLICT,
                );
            }

            $this->orderAttachmentStore->deleteStoredFile($attachment);
            $attachment->delete();
        });
    }
}
