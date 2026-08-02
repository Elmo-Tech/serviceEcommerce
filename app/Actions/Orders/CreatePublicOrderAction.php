<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\HttpStatusCode;
use App\Enums\Orders\OrderPlace;
use App\Enums\Orders\OrderStatus;
use App\Enums\Orders\PaymentStatus;
use App\Exceptions\ApiBusinessException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Services\Orders\CustomerOrderResolver;
use App\Services\Orders\OrderAttachmentStore;
use App\Services\Orders\OrderNumberAllocator;
use App\Services\Orders\OrderPaymentSummaryService;
use App\Services\Orders\OrderPricingService;
use App\Services\Orders\OrderSnapshotFactory;
use App\Services\Orders\PublicOrderIdempotencyService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreatePublicOrderAction
{
    public function __construct(
        private readonly CustomerOrderResolver $customerOrderResolver,
        private readonly OrderNumberAllocator $orderNumberAllocator,
        private readonly OrderSnapshotFactory $orderSnapshotFactory,
        private readonly OrderPricingService $orderPricingService,
        private readonly OrderPaymentSummaryService $orderPaymentSummaryService,
        private readonly OrderAttachmentStore $orderAttachmentStore,
        private readonly PublicOrderIdempotencyService $publicOrderIdempotencyService,
    ) {}

    /**
     * @return array{order:Order,isReplay:bool}
     */
    public function execute(string $idempotencyKey, array $payload): array
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($idempotencyKey, $payload, &$storedFiles): array {
                $fingerprint = $this->publicOrderIdempotencyService->fingerprint($payload);
                $reservation = $this->publicOrderIdempotencyService->reserve($idempotencyKey, $fingerprint, now());

                if ($reservation['isReplay'] === true && $reservation['replayOrder'] instanceof Order) {
                    return [
                        'order' => $reservation['replayOrder'],
                        'isReplay' => true,
                    ];
                }

                $submittedCustomer = $this->customerOrderResolver->normalizeSubmittedCustomer($payload['customer']);
                $customer = $this->customerOrderResolver->resolvePublicCustomer($payload['customer']);
                $orderNumber = $this->orderNumberAllocator->allocate(now());

                $order = Order::query()->create([
                    ...$this->orderSnapshotFactory->customerSnapshot($customer, $submittedCustomer),
                    ...$this->orderSnapshotFactory->addressSnapshot(
                        null,
                        is_array($payload['address'] ?? null) ? $payload['address'] : null,
                    ),
                    'order_number' => $orderNumber,
                    'status' => OrderStatus::PENDING,
                    'order_place' => OrderPlace::WEBSITE,
                    'customer_note' => $payload['customerNote'] ?? null,
                    'admin_note' => null,
                    'subtotal' => '0.00',
                    'discount_type' => null,
                    'discount_value' => '0.00',
                    'discount_amount' => '0.00',
                    'discount_reason' => null,
                    'total' => '0.00',
                    'payment_status' => PaymentStatus::UNPAID,
                    'paid_amount' => '0.00',
                    'created_by_admin_id' => null,
                ]);

                $itemTotals = [];

                foreach ($payload['items'] as $itemPayload) {
                    $orderItem = $this->createOrderItem($order, $itemPayload, $storedFiles);
                    $itemTotals[] = $orderItem->item_total;
                }

                $subtotal = $this->orderPricingService->calculateSubtotal($itemTotals);
                $paymentSummary = $this->orderPaymentSummaryService->summarize($subtotal, '0.00');

                $order->forceFill([
                    'subtotal' => $subtotal,
                    'discount_amount' => '0.00',
                    'total' => $subtotal,
                    'payment_status' => $paymentSummary['paymentStatus'],
                    'paid_amount' => '0.00',
                ])->save();

                $this->publicOrderIdempotencyService->complete($reservation['reservation'], $order, now());

                return [
                    'order' => $order->fresh(['items']) ?? $order,
                    'isReplay' => false,
                ];
            }, 3);
        } catch (\Throwable $throwable) {
            $this->orderAttachmentStore->cleanupCreatedFiles($storedFiles);

            throw $throwable;
        }
    }

    /**
     * @param  array<string,mixed>  $itemPayload
     * @param  list<array{disk:string,path:string}>  $storedFiles
     */
    private function createOrderItem(Order $order, array $itemPayload, array &$storedFiles): OrderItem
    {
        $service = $this->resolveEligibleService((int) $itemPayload['serviceId']);
        $pricing = $this->orderPricingService->priceService($service, $itemPayload['selectedOptions'] ?? []);
        $this->validateAndMapAnswers($service, $itemPayload['answers'] ?? []);
        $serviceSnapshot = $this->orderSnapshotFactory->serviceSnapshot($service);

        $orderItem = $order->items()->create([
            ...$serviceSnapshot,
            'unit_price' => $pricing['unitPrice'],
            'quantity' => (int) $itemPayload['quantity'],
            'item_total' => $this->orderPricingService->calculateItemTotal($pricing['unitPrice'], (int) $itemPayload['quantity']),
            'item_note' => $itemPayload['itemNote'] ?? null,
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

        foreach ($this->validateAndMapAnswers($service, $itemPayload['answers'] ?? []) as $answerSnapshot) {
            $orderItem->answers()->create($answerSnapshot);
        }

        foreach (($itemPayload['attachments'] ?? []) as $attachment) {
            if (! $attachment instanceof UploadedFile) {
                continue;
            }

            $stored = $this->orderAttachmentStore->storeUploadedFile($attachment, $order->order_number, (int) $orderItem->getKey());
            $storedFiles[] = [
                'disk' => $stored['disk'],
                'path' => $stored['path'],
            ];
            $orderItem->attachments()->create($stored);
        }

        return $orderItem->fresh(['selectedOptions.values', 'answers', 'attachments']) ?? $orderItem;
    }

    private function resolveEligibleService(int $serviceId): Service
    {
        $service = Service::query()
            ->with([
                'pricingOptions.values',
                'orderFields',
            ])
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
