<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Models\Order;
use App\Services\Orders\OrderPaymentSummaryService;
use Illuminate\Support\Facades\DB;

class UpdateOrderPaymentAction
{
    public function __construct(
        private readonly OrderPaymentSummaryService $orderPaymentSummaryService,
    ) {}

    public function execute(Order $order, string $paidAmount): Order
    {
        return DB::transaction(function () use ($order, $paidAmount): Order {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $paymentSummary = $this->orderPaymentSummaryService->summarize(
                (string) $lockedOrder->total,
                $paidAmount,
            );

            $lockedOrder->forceFill([
                'paid_amount' => $paidAmount,
                'payment_status' => $paymentSummary['paymentStatus'],
            ])->save();

            return $lockedOrder->fresh() ?? $lockedOrder;
        });
    }
}
