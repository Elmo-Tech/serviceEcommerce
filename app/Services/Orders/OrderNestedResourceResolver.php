<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAttachment;

class OrderNestedResourceResolver
{
    public function resolveOrderItem(Order $order, int $orderItemId): OrderItem
    {
        $orderItem = $order->items()
            ->whereKey($orderItemId)
            ->first();

        if (! $orderItem instanceof OrderItem) {
            throw new ApiBusinessException(
                'auth.resource_not_found',
                'RESOURCE_NOT_FOUND',
                HttpStatusCode::NOT_FOUND,
            );
        }

        return $orderItem;
    }

    public function resolveAttachment(Order $order, int $orderItemId, int $attachmentId): OrderItemAttachment
    {
        $orderItem = $this->resolveOrderItem($order, $orderItemId);

        $attachment = $orderItem->attachments()
            ->whereKey($attachmentId)
            ->first();

        if (! $attachment instanceof OrderItemAttachment) {
            throw new ApiBusinessException(
                'auth.resource_not_found',
                'RESOURCE_NOT_FOUND',
                HttpStatusCode::NOT_FOUND,
            );
        }

        return $attachment;
    }
}
