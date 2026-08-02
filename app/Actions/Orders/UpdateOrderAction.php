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
use App\Models\User;
use App\Services\Orders\CustomerOrderResolver;
use App\Services\Orders\OrderPaymentSummaryService;
use App\Services\Orders\OrderPricingService;
use App\Services\Orders\OrderSnapshotFactory;
use App\Services\Orders\OrderStatusTransitionService;
use Illuminate\Support\Facades\DB;

class UpdateOrderAction
{
    public function __construct(
        private readonly CustomerOrderResolver $customerOrderResolver,
        private readonly OrderSnapshotFactory $orderSnapshotFactory,
        private readonly OrderPricingService $orderPricingService,
        private readonly OrderPaymentSummaryService $orderPaymentSummaryService,
        private readonly OrderStatusTransitionService $orderStatusTransitionService,
        private readonly CreateCustomerAddressAction $createCustomerAddressAction,
    ) {}

    public function execute(Order $order, array $payload, User $admin): Order
    {
        return DB::transaction(function () use ($order, $payload, $admin): Order {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->with(['customer', 'items'])
                ->lockForUpdate()
                ->firstOrFail();

            $hasStatusChange = array_key_exists('status', $payload);
            $hasEditableFieldChanges = $this->hasEditableFieldChanges($payload);

            if ($hasEditableFieldChanges && ! $lockedOrder->isEditable()) {
                throw new ApiBusinessException(
                    'orders.errors.order_not_editable',
                    'ORDER_NOT_EDITABLE',
                    HttpStatusCode::CONFLICT,
                );
            }

            $customerChanged = array_key_exists('customerId', $payload) || array_key_exists('customer', $payload);
            $customer = $customerChanged
                ? $this->customerOrderResolver->resolveAdminCustomer(
                    isset($payload['customerId']) ? (int) $payload['customerId'] : null,
                    is_array($payload['customer'] ?? null) ? $payload['customer'] : null,
                )
                : $lockedOrder->customer;

            $submittedCustomer = is_array($payload['customer'] ?? null)
                ? $this->customerOrderResolver->normalizeSubmittedCustomer($payload['customer'])
                : null;

            $customerAttributes = $customerChanged
                ? $this->orderSnapshotFactory->customerSnapshot($customer, $submittedCustomer)
                : [
                    'customer_id' => $lockedOrder->customer_id,
                    'customer_name' => $lockedOrder->customer_name,
                    'customer_phone' => $lockedOrder->customer_phone,
                    'customer_email' => $lockedOrder->customer_email,
                ];

            $addressAttributes = $this->resolveAddressAttributes($lockedOrder, $customer, $payload, $customerChanged);

            $subtotal = $this->orderPricingService->calculateSubtotal(
                $lockedOrder->items->pluck('item_total')->all(),
            );
            [$discountType, $discountValue, $discountAmount, $discountReason] = $this->resolveDiscountForUpdate(
                $lockedOrder,
                $payload,
                $subtotal,
            );
            $total = $this->orderPricingService->calculateTotal($subtotal, $discountAmount);
            $paymentSummary = $this->orderPaymentSummaryService->summarize(
                $total,
                (string) $lockedOrder->paid_amount,
            );

            $attributes = [
                ...$customerAttributes,
                ...$addressAttributes,
                'order_place' => array_key_exists('orderPlace', $payload)
                    ? (int) $payload['orderPlace']
                    : $lockedOrder->order_place?->value,
                'customer_note' => array_key_exists('customerNote', $payload)
                    ? $payload['customerNote']
                    : $lockedOrder->customer_note,
                'admin_note' => array_key_exists('adminNote', $payload)
                    ? $payload['adminNote']
                    : $lockedOrder->admin_note,
                'subtotal' => $subtotal,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $discountAmount,
                'discount_reason' => $discountReason,
                'total' => $total,
                'payment_status' => $paymentSummary['paymentStatus'],
            ];

            if ($hasStatusChange) {
                $targetStatus = OrderStatus::from((int) $payload['status']);
                $reason = $payload['reason'] ?? null;

                $this->orderStatusTransitionService->ensureTransitionAllowed(
                    $lockedOrder->status,
                    $targetStatus,
                    $reason,
                );

                $attributes['status'] = $targetStatus;
                $attributes['cancellation_reason'] = $targetStatus === OrderStatus::CANCELLED ? $reason : null;
                $attributes['cancelled_at'] = $targetStatus === OrderStatus::CANCELLED ? now() : null;
                $attributes['cancelled_by_admin_id'] = $targetStatus === OrderStatus::CANCELLED ? $admin->getKey() : null;
            }

            $lockedOrder->forceFill($attributes)->save();

            return $lockedOrder->fresh([
                'cancelledByAdmin',
                'createdByAdmin',
                'items.selectedOptions.values',
                'items.answers',
                'items.attachments',
            ]) ?? $lockedOrder;
        });
    }

    private function hasEditableFieldChanges(array $payload): bool
    {
        foreach ([
            'customerId',
            'customer',
            'customerAddressId',
            'address',
            'customerNote',
            'adminNote',
            'discountType',
            'discountValue',
            'discountReason',
            'orderPlace',
        ] as $key) {
            if (array_key_exists($key, $payload)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,mixed>
     */
    private function resolveAddressAttributes(Order $order, ?Customer $customer, array $payload, bool $customerChanged): array
    {
        $addressChanged = array_key_exists('customerAddressId', $payload) || array_key_exists('address', $payload);

        if (! $addressChanged) {
            if ($customerChanged) {
                return [
                    'customer_address_id' => null,
                    'address_province' => null,
                    'address_city' => null,
                    'address_text' => null,
                ];
            }

            return [
                'customer_address_id' => $order->customer_address_id,
                'address_province' => $order->address_province,
                'address_city' => $order->address_city,
                'address_text' => $order->address_text,
            ];
        }

        if (array_key_exists('address', $payload) && $payload['address'] === null) {
            return [
                'customer_address_id' => null,
                'address_province' => null,
                'address_city' => null,
                'address_text' => null,
            ];
        }

        if (! $customer instanceof Customer) {
            throw new ApiBusinessException(
                'orders.errors.customer_not_found',
                'CUSTOMER_NOT_FOUND',
                HttpStatusCode::NOT_FOUND,
            );
        }

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

            return $this->orderSnapshotFactory->addressSnapshot($address, null);
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

            return $this->orderSnapshotFactory->addressSnapshot($savedAddress, null);
        }

        return [
            'customer_address_id' => $order->customer_address_id,
            'address_province' => $order->address_province,
            'address_city' => $order->address_city,
            'address_text' => $order->address_text,
        ];
    }

    /**
     * @return array{0:?int,1:string,2:string,3:?string}
     */
    private function resolveDiscountForUpdate(Order $order, array $payload, string $subtotal): array
    {
        $hasDiscountMutation = array_key_exists('discountType', $payload)
            || array_key_exists('discountValue', $payload)
            || array_key_exists('discountReason', $payload);

        if (! $hasDiscountMutation) {
            return [
                $order->discount_type?->value,
                number_format((float) $order->discount_value, 2, '.', ''),
                number_format((float) $order->discount_amount, 2, '.', ''),
                $order->discount_reason,
            ];
        }

        if (($payload['discountType'] ?? null) === null) {
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
}
