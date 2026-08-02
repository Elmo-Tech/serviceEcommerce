<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Services\Orders\OrderPricingService;
use App\Services\Orders\OrderSnapshotFactory;
use Illuminate\Support\Facades\DB;

class AddOrderItemAction
{
    public function __construct(
        private readonly OrderSnapshotFactory $orderSnapshotFactory,
        private readonly OrderPricingService $orderPricingService,
    ) {}

    public function execute(Order $order, array $payload): OrderItem
    {
        return DB::transaction(function () use ($order, $payload): OrderItem {
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

            $service = $this->resolveEligibleService((int) $payload['serviceId']);
            $pricing = $this->orderPricingService->priceService($service, $payload['selectedOptions'] ?? []);
            $answerSnapshots = $this->validateAndMapAnswers($service, $payload['answers'] ?? []);

            $quantity = (int) $payload['quantity'];
            $itemTotal = $this->orderPricingService->calculateItemTotal($pricing['unitPrice'], $quantity);

            $orderItem = $lockedOrder->items()->create([
                ...$this->orderSnapshotFactory->serviceSnapshot($service),
                'unit_price' => $pricing['unitPrice'],
                'quantity' => $quantity,
                'item_total' => $itemTotal,
                'item_note' => $payload['itemNote'] ?? null,
            ]);

            foreach ($pricing['selectedOptions'] as $selectedOption) {
                $persistedOption = $orderItem->selectedOptions()->create([
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

            foreach ($answerSnapshots as $answerSnapshot) {
                $orderItem->answers()->create($answerSnapshot);
            }

            $this->recalculateOrderTotals($lockedOrder);

            return $orderItem->fresh(['selectedOptions.values', 'answers', 'attachments']) ?? $orderItem;
        });
    }

    private function resolveEligibleService(int $serviceId): Service
    {
        $service = Service::query()
            ->with(['pricingOptions.values', 'orderFields'])
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

    private function recalculateOrderTotals(Order $order): void
    {
        $itemTotals = $order->items()->pluck('item_total')->all();
        $subtotal = $this->orderPricingService->calculateSubtotal($itemTotals);
        $total = $this->orderPricingService->calculateTotal($subtotal, (string) $order->discount_amount);

        $order->forceFill([
            'subtotal' => $subtotal,
            'total' => $total,
        ])->save();
    }

    /**
     * @param  list<array{orderFieldId:int,answer:string}>  $answers
     * @return list<array<string,mixed>>
     */
    private function validateAndMapAnswers(Service $service, array $answers): array
    {
        $fieldMap = $service->orderFields
            ->filter(fn ($field) => $field->deleted_at === null)
            ->keyBy(fn ($field) => (int) $field->getKey());

        $answeredFieldIds = [];
        $snapshots = [];

        foreach ($answers as $answerPayload) {
            $fieldId = (int) $answerPayload['orderFieldId'];
            $field = $fieldMap->get($fieldId);

            if (! $field) {
                throw new ApiBusinessException(
                    'orders.errors.order_field_not_found',
                    'ORDER_FIELD_NOT_FOUND',
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

            $answeredFieldIds[] = $fieldId;
            $snapshots[] = $this->orderSnapshotFactory->answerSnapshot($field, $answerText);
        }

        foreach ($fieldMap as $field) {
            if ($field->is_required && ! in_array((int) $field->getKey(), $answeredFieldIds, true)) {
                throw new ApiBusinessException(
                    'orders.errors.required_order_field_missing',
                    'REQUIRED_ORDER_FIELD_MISSING',
                    HttpStatusCode::UNPROCESSABLE_ENTITY,
                );
            }
        }

        return $snapshots;
    }
}
