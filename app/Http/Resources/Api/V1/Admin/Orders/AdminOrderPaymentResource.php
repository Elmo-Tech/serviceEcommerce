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
class AdminOrderPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payment = app(OrderPaymentSummaryService::class)->summarize(
            (string) $this->total,
            (string) $this->paid_amount,
        );

        return [
            'total' => $this->formatMoney($this->total),
            'paymentStatus' => $this->payment_status?->value,
            'paidAmount' => $this->formatMoney($this->paid_amount),
            'remainingAmount' => $payment['remainingAmount'],
        ];
    }

    private function formatMoney(string|int|float|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
