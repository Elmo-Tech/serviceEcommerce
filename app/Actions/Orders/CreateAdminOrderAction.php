<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\CustomerAddresses\CreateCustomerAddressAction;
use App\Enums\HttpStatusCode;
use App\Enums\Orders\DiscountType;
use App\Enums\Orders\OrderStatus;
use App\Exceptions\ApiBusinessException;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\User;
use App\Services\Orders\CustomerOrderResolver;
use App\Services\Orders\OrderAttachmentStore;
use App\Services\Orders\OrderNumberAllocator;
use App\Services\Orders\OrderPaymentSummaryService;
use App\Services\Orders\OrderPricingService;
use App\Services\Orders\OrderSnapshotFactory;
use App\Services\Orders\RequiredServiceAttachmentValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateAdminOrderAction
{
    public function __construct(
        private readonly CustomerOrderResolver $customerOrderResolver,
        private readonly OrderNumberAllocator $orderNumberAllocator,
        private readonly OrderSnapshotFactory $orderSnapshotFactory,
        private readonly OrderPricingService $orderPricingService,
        private readonly OrderPaymentSummaryService $orderPaymentSummaryService,
        private readonly OrderAttachmentStore $orderAttachmentStore,
        private readonly CreateCustomerAddressAction $createCustomerAddressAction,
        private readonly RequiredServiceAttachmentValidator $requiredServiceAttachmentValidator,
    ) {}

    public function execute(array $payload, User $admin): Order
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($payload, $admin, &$storedFiles): Order {
                $customer = $this->customerOrderResolver->resolveAdminCustomer(
                    isset($payload['customerId']) ? (int) $payload['customerId'] : null,
                    is_array($payload['customer'] ?? null) ? $payload['customer'] : null,
                );

                $submittedCustomer = is_array($payload['customer'] ?? null)
                    ? $this->customerOrderResolver->normalizeSubmittedCustomer($payload['customer'])
                    : null;

                $resolvedAddress = $this->resolveAdminAddress($customer, $payload);
                $orderNumber = $this->orderNumberAllocator->allocate(now());

                $itemTotals = [];
                $preparedItems = [];

                foreach ($payload['items'] as $itemPayload) {
                    $preparedItems[] = $this->prepareItemPayload($itemPayload);
                    $itemTotals[] = end($preparedItems)['itemTotal'];
                }

                $subtotal = $this->orderPricingService->calculateSubtotal($itemTotals);
                [$discountType, $discountValue, $discountAmount, $discountReason] = $this->resolveDiscount($payload, $subtotal);
                $total = $this->orderPricingService->calculateTotal($subtotal, $discountAmount);
                $paymentSummary = $this->orderPaymentSummaryService->summarize($total, '0.00');

                $order = Order::query()->create([
                    ...$this->orderSnapshotFactory->customerSnapshot($customer, $submittedCustomer),
                    ...$this->orderSnapshotFactory->addressSnapshot(
                        $resolvedAddress['savedAddress'],
                        $resolvedAddress['snapshotAddress'],
                    ),
                    'order_number' => $orderNumber,
                    'status' => OrderStatus::PENDING,
                    'order_place' => (int) $payload['orderPlace'],
                    'customer_note' => $payload['customerNote'] ?? null,
                    'admin_note' => $payload['adminNote'] ?? null,
                    'subtotal' => $subtotal,
                    'discount_type' => $discountType,
                    'discount_value' => $discountValue,
                    'discount_amount' => $discountAmount,
                    'discount_reason' => $discountReason,
                    'total' => $total,
                    'payment_status' => $paymentSummary['paymentStatus'],
                    'paid_amount' => '0.00',
                    'created_by_admin_id' => $admin->getKey(),
                ]);

                foreach ($preparedItems as $preparedItem) {
                    $this->persistPreparedItem($order, $preparedItem, $storedFiles);
                }

                return $order->fresh(['items']) ?? $order;
            }, 3);
        } catch (\Throwable $throwable) {
            $this->orderAttachmentStore->cleanupCreatedFiles($storedFiles);

            throw $throwable;
        }
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{savedAddress:?CustomerAddress,snapshotAddress:?array}
     */
    private function resolveAdminAddress(Customer $customer, array $payload): array
    {
        if (isset($payload['customerAddressId'])) {
            $address = CustomerAddress::query()
                ->where('customer_id', $customer->getKey())
                ->find((int) $payload['customerAddressId']);

            if (! $address instanceof CustomerAddress) {
                throw new ApiBusinessException(
                    'orders.errors.customer_address_not_found',
                    'CUSTOMER_ADDRESS_NOT_FOUND',
                    HttpStatusCode::NOT_FOUND,
                );
            }

            return [
                'savedAddress' => $address,
                'snapshotAddress' => null,
            ];
        }

        if (is_array($payload['address'] ?? null)) {
            $savedAddress = $this->createCustomerAddressAction->execute($customer, [
                'phone' => $customer->phone,
                'phoneCountryCode' => 'EG',
                'province' => $payload['address']['province'],
                'city' => $payload['address']['city'],
                'address' => $payload['address']['address'],
                'notes' => null,
                'isDefault' => false,
            ]);

            return [
                'savedAddress' => $savedAddress,
                'snapshotAddress' => null,
            ];
        }

        return [
            'savedAddress' => null,
            'snapshotAddress' => null,
        ];
    }

    /**
     * @param  array<string,mixed>  $itemPayload
     * @return array<string,mixed>
     */
    private function prepareItemPayload(array $itemPayload): array
    {
        $service = $this->resolveEligibleService((int) $itemPayload['serviceId']);
        $this->requiredServiceAttachmentValidator->validate($service, (array) ($itemPayload['attachments'] ?? []));
        $pricing = $this->orderPricingService->priceService($service, $itemPayload['selectedOptions'] ?? []);
        $answerSnapshots = $this->validateAndMapAnswers($service, $itemPayload['answers'] ?? []);

        return [
            'service' => $service,
            'serviceSnapshot' => $this->orderSnapshotFactory->serviceSnapshot($service),
            'pricing' => $pricing,
            'quantity' => (int) $itemPayload['quantity'],
            'itemTotal' => $this->orderPricingService->calculateItemTotal($pricing['unitPrice'], (int) $itemPayload['quantity']),
            'itemNote' => $itemPayload['itemNote'] ?? null,
            'answerSnapshots' => $answerSnapshots,
            'attachments' => $itemPayload['attachments'] ?? [],
        ];
    }

    /**
     * @param  array<string,mixed>  $preparedItem
     * @param  list<array{disk:string,path:string}>  $storedFiles
     */
    private function persistPreparedItem(Order $order, array $preparedItem, array &$storedFiles): OrderItem
    {
        $orderItem = $order->items()->create([
            ...$preparedItem['serviceSnapshot'],
            'unit_price' => $preparedItem['pricing']['unitPrice'],
            'quantity' => $preparedItem['quantity'],
            'item_total' => $preparedItem['itemTotal'],
            'item_note' => $preparedItem['itemNote'],
        ]);

        foreach ($preparedItem['pricing']['selectedOptions'] as $selectedOption) {
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

        foreach ($preparedItem['answerSnapshots'] as $answerSnapshot) {
            $orderItem->answers()->create($answerSnapshot);
        }

        foreach ($preparedItem['attachments'] as $attachment) {
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

        return $orderItem;
    }

    /**
     * @return array{0:?int,1:string,2:string,3:?string}
     */
    private function resolveDiscount(array $payload, string $subtotal): array
    {
        if (! array_key_exists('discountType', $payload) || $payload['discountType'] === null) {
            return [null, '0.00', '0.00', null];
        }

        $discountType = (int) $payload['discountType'];
        $discountValue = number_format((float) ($payload['discountValue'] ?? 0), 2, '.', '');
        $discountReason = trim((string) ($payload['discountReason'] ?? ''));

        if ($discountReason === '') {
            throw new ApiBusinessException(
                'orders.errors.invalid_discount',
                'VALIDATION_ERROR',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }

        if ($discountType === DiscountType::FIXED->value && (float) $discountValue > (float) $subtotal) {
            throw new ApiBusinessException(
                'orders.errors.invalid_discount',
                'VALIDATION_ERROR',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }

        if ($discountType === DiscountType::PERCENTAGE->value && (float) $discountValue > 100.0) {
            throw new ApiBusinessException(
                'orders.errors.invalid_discount',
                'VALIDATION_ERROR',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }

        $enum = DiscountType::from($discountType);
        $discountAmount = $this->orderPricingService->calculateDiscountAmount($subtotal, $enum, $discountValue);

        return [$discountType, $discountValue, $discountAmount, $discountReason];
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
