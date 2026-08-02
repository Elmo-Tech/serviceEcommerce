<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Orders;

use App\Models\Order;
use App\Services\Orders\OrderPaymentSummaryService;
use App\Services\Orders\OrderStatusTransitionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class AdminOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payment = app(OrderPaymentSummaryService::class)->summarize(
            (string) $this->total,
            (string) $this->paid_amount,
        );
        $availableTransitions = app(OrderStatusTransitionService::class)
            ->availableTransitions($this->status)
        ;

        return [
            'id' => $this->id,
            'orderNumber' => $this->order_number,
            'status' => $this->status?->value,
            'availableStatusTransitions' => array_values(array_map(
                static fn ($status) => $status->value,
                $availableTransitions,
            )),
            'orderPlace' => $this->order_place?->value,
            'customerId' => $this->customer_id,
            'customerSnapshot' => [
                'name' => $this->customer_name,
                'phone' => $this->customer_phone,
                'email' => $this->customer_email,
            ],
            'customerAddressId' => $this->customer_address_id,
            'addressSnapshot' => $this->address_province === null && $this->address_city === null && $this->address_text === null
                ? null
                : [
                    'province' => $this->address_province,
                    'city' => $this->address_city,
                    'address' => $this->address_text,
                ],
            'customerNote' => $this->customer_note,
            'adminNote' => $this->admin_note,
            'subtotal' => $this->formatMoney($this->subtotal),
            'discount' => $this->discount_type === null
                ? null
                : [
                    'type' => $this->discount_type->value,
                    'value' => $this->formatMoney($this->discount_value),
                    'amount' => $this->formatMoney($this->discount_amount),
                    'reason' => $this->discount_reason,
                ],
            'total' => $this->formatMoney($this->total),
            'payment' => [
                'paymentStatus' => $this->payment_status?->value,
                'paidAmount' => $this->formatMoney($this->paid_amount),
                'remainingAmount' => $payment['remainingAmount'],
            ],
            'cancellation' => $this->cancelled_at === null
                ? null
                : [
                    'reason' => $this->cancellation_reason,
                    'cancelledAt' => $this->cancelled_at?->toJSON(),
                    'cancelledByAdmin' => $this->cancelledByAdmin === null ? null : [
                        'id' => $this->cancelledByAdmin->getKey(),
                        'name' => $this->cancelledByAdmin->name,
                    ],
                ],
            'items' => AdminOrderItemResource::collection($this->whenLoaded('items'))->resolve(),
            'createdByAdmin' => $this->createdByAdmin === null ? null : [
                'id' => $this->createdByAdmin->getKey(),
                'name' => $this->createdByAdmin->name,
            ],
            'createdAt' => $this->created_at?->toJSON(),
            'updatedAt' => $this->updated_at?->toJSON(),
        ];
    }

    private function formatMoney(string|int|float|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
