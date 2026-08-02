<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Orders;

use App\Models\Order;
use App\Services\Orders\OrderPaymentSummaryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class AdminOrderIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payment = app(OrderPaymentSummaryService::class)->summarize(
            (string) $this->total,
            (string) $this->paid_amount,
        );

        return [
            'id' => $this->id,
            'orderNumber' => $this->order_number,
            'customerName' => $this->customer_name,
            'customerPhone' => $this->customer_phone,
            'status' => $this->status?->value,
            'paymentStatus' => $this->payment_status?->value,
            'orderPlace' => $this->order_place?->value,
            'itemsCount' => (int) ($this->items_count ?? 0),
            'subtotal' => $this->formatMoney($this->subtotal),
            'discountAmount' => $this->formatMoney($this->discount_amount),
            'total' => $this->formatMoney($this->total),
            'paidAmount' => $this->formatMoney($this->paid_amount),
            'remainingAmount' => $payment['remainingAmount'],
            'createdAt' => $this->created_at?->toJSON(),
        ];
    }

    private function formatMoney(string|int|float|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
