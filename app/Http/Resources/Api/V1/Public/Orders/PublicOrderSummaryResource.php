<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Public\Orders;

use App\Models\Order;
use App\Services\Orders\OrderPaymentSummaryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class PublicOrderSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payment = app(OrderPaymentSummaryService::class)->summarize(
            (string) $this->total,
            (string) $this->paid_amount,
        );

        return [
            'orderNumber' => $this->order_number,
            'status' => $this->status?->value,
            'paymentStatus' => $this->payment_status?->value,
            'subtotal' => $this->formatMoney($this->subtotal),
            'discountAmount' => $this->formatMoney($this->discount_amount),
            'total' => $this->formatMoney($this->total),
            'paidAmount' => $this->formatMoney($this->paid_amount),
            'remainingAmount' => $payment['remainingAmount'],
        ];
    }

    private function formatMoney(string|int|float|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
