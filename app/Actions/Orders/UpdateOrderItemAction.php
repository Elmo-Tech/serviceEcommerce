<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAnswer;
use App\Models\Service;
use App\Services\Orders\OrderPaymentSummaryService;
use App\Services\Orders\OrderPricingService;
use App\Services\Orders\OrderSnapshotFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UpdateOrderItemAction
{
    public function __construct(
        private readonly OrderPricingService $orderPricingService,
        private readonly OrderPaymentSummaryService $orderPaymentSummaryService,
        private readonly OrderSnapshotFactory $orderSnapshotFactory,
    ) {}

    public function execute(Order $order, OrderItem $orderItem, array $payload): OrderItem
    {
        return DB::transaction(function () use ($order, $orderItem, $payload): OrderItem {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedOrder->isEditable()) {
                throw new ApiBusinessException(
                    'orders.errors.order_not_editable',
                    'ORDER_NOT_EDITABLE',
                    HttpStatusCode::CONFLICT,
                );
            }

            /** @var OrderItem $lockedItem */
            $lockedItem = $lockedOrder->items()
                ->whereKey($orderItem->getKey())
                ->with(['selectedOptions.values', 'answers'])
                ->firstOrFail();

            $unitPrice = (string) $lockedItem->unit_price;
            $quantity = array_key_exists('quantity', $payload)
                ? (int) $payload['quantity']
                : (int) $lockedItem->quantity;

            if (array_key_exists('selectedOptions', $payload)) {
                $service = $this->resolveEligibleService((int) $lockedItem->service_id);
                $pricing = $this->orderPricingService->priceService($service, $payload['selectedOptions'] ?? []);
                $unitPrice = $pricing['unitPrice'];

                $lockedItem->selectedOptions()->delete();

                foreach ($pricing['selectedOptions'] as $selectedOption) {
                    $persistedOption = $lockedItem->selectedOptions()->create([
                        'pricing_option_id' => $selectedOption['option']->getKey(),
                        'option_name_ar' => $selectedOption['option']->name_ar,
                        'option_name_en' => $selectedOption['option']->name_en,
                        'input_type' => $selectedOption['option']->input_type->value,
                        'is_required' => (bool) $selectedOption['option']->is_required,
                    ]);

                    foreach ($selectedOption['values'] as $value) {
                        $persistedOption->values()->create([
                            'pricing_option_value_id' => $value->getKey(),
                            'value_label_ar' => $value->label_ar,
                            'value_label_en' => $value->label_en,
                            'price_adjustment' => $value->price_adjustment,
                        ]);
                    }
                }
            }

            if (array_key_exists('answers', $payload)) {
                $this->replaceAnswers($lockedItem, (array) $payload['answers']);
            }

            $lockedItem->forceFill([
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'item_total' => $this->orderPricingService->calculateItemTotal($unitPrice, $quantity),
                'item_note' => array_key_exists('itemNote', $payload) ? $payload['itemNote'] : $lockedItem->item_note,
            ])->save();

            $this->recalculateOrderTotals($lockedOrder);

            return $lockedItem->fresh(['selectedOptions.values', 'answers', 'attachments']) ?? $lockedItem;
        });
    }

    private function resolveEligibleService(int $serviceId): Service
    {
        $service = Service::query()
            ->with(['pricingOptions.values'])
            ->find($serviceId);

        if (! $service instanceof Service || $service->trashed() || ! $service->is_active) {
            throw new ApiBusinessException(
                'services.errors.not_found',
                'SERVICE_NOT_FOUND',
                HttpStatusCode::NOT_FOUND,
            );
        }

        if (! $service->is_available) {
            throw new ApiBusinessException(
                'orders.errors.service_unavailable',
                'SERVICE_UNAVAILABLE',
                HttpStatusCode::CONFLICT,
            );
        }

        return $service;
    }

    /**
     * @param  list<array{orderItemAnswerId:int,answer:string}>  $answersPayload
     */
    private function replaceAnswers(OrderItem $orderItem, array $answersPayload): void
    {
        /** @var Collection<int,OrderItemAnswer> $storedAnswers */
        $storedAnswers = $orderItem->answers()->get()->keyBy(fn (OrderItemAnswer $answer) => (int) $answer->getKey());
        $submittedIds = [];

        foreach ($answersPayload as $answerPayload) {
            $answerId = (int) $answerPayload['orderItemAnswerId'];
            $storedAnswer = $storedAnswers->get($answerId);

            if (! $storedAnswer instanceof OrderItemAnswer) {
                throw new ApiBusinessException(
                    'auth.resource_not_found',
                    'RESOURCE_NOT_FOUND',
                    HttpStatusCode::NOT_FOUND,
                );
            }

            $answerText = trim((string) $answerPayload['answer']);

            if ($answerText === '') {
                throw new ApiBusinessException(
                    'orders.errors.invalid_order_field_answer',
                    'INVALID_ORDER_FIELD_ANSWER',
                    HttpStatusCode::UNPROCESSABLE_ENTITY,
                );
            }

            $storedAnswer->forceFill([
                'answer' => $answerText,
            ])->save();

            $submittedIds[] = $answerId;
        }

        foreach ($storedAnswers as $storedAnswer) {
            $answerId = (int) $storedAnswer->getKey();

            if ($storedAnswer->is_required && ! in_array($answerId, $submittedIds, true)) {
                throw new ApiBusinessException(
                    'orders.errors.required_order_field_missing',
                    'REQUIRED_ORDER_FIELD_MISSING',
                    HttpStatusCode::UNPROCESSABLE_ENTITY,
                );
            }

            if (! $storedAnswer->is_required && ! in_array($answerId, $submittedIds, true)) {
                $storedAnswer->delete();
            }
        }
    }

    private function recalculateOrderTotals(Order $order): void
    {
        $itemTotals = $order->items()->pluck('item_total')->all();
        $subtotal = $this->orderPricingService->calculateSubtotal($itemTotals);
        $total = $this->orderPricingService->calculateTotal($subtotal, (string) $order->discount_amount);
        $paymentSummary = $this->orderPaymentSummaryService->summarize(
            $total,
            (string) $order->paid_amount,
        );

        $order->forceFill([
            'subtotal' => $subtotal,
            'total' => $total,
            'payment_status' => $paymentSummary['paymentStatus'],
        ])->save();
    }
}
