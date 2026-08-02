<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Orders;

use App\Models\OrderItemAttachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItemAttachment
 */
class AdminOrderAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $orderRouteValue = $request->route('order');
        $orderId = $orderRouteValue instanceof Model
            ? (int) $orderRouteValue->getKey()
            : (int) $orderRouteValue;
        $orderItemId = $this->order_item_id;

        return [
            'id' => $this->id,
            'originalName' => $this->original_name,
            'mimeType' => $this->mime_type,
            'extension' => $this->extension,
            'sizeBytes' => (int) $this->size_bytes,
            'downloadEndpoint' => $orderId > 0 && is_int($orderItemId)
                ? "/api/v1/admin/orders/{$orderId}/items/{$orderItemId}/attachments/{$this->id}/download"
                : null,
            'createdAt' => $this->created_at?->toJSON(),
        ];
    }
}
