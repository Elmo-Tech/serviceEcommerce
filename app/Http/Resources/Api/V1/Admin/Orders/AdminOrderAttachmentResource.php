<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Orders;

use App\Models\OrderItemAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItemAttachment
 */
class AdminOrderAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $orderItem = $this->orderItem;
        $orderId = $orderItem?->order_id;
        $orderItemId = $orderItem?->getKey();

        return [
            'id' => $this->id,
            'originalName' => $this->original_name,
            'mimeType' => $this->mime_type,
            'extension' => $this->extension,
            'sizeBytes' => (int) $this->size_bytes,
            'downloadEndpoint' => is_int($orderId) && is_int($orderItemId)
                ? "/api/v1/admin/orders/{$orderId}/items/{$orderItemId}/attachments/{$this->id}/download"
                : null,
            'createdAt' => $this->created_at?->toJSON(),
        ];
    }
}
