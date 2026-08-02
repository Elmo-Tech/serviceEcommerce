<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Orders;

use App\Enums\Orders\DiscountType;
use App\Enums\Orders\OrderPlace;
use App\Enums\Orders\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customerId' => ['sometimes', 'integer', 'min:1'],
            'customer' => ['sometimes', 'array'],
            'customer.name' => ['required_with:customer', 'string', 'min:1', 'max:150'],
            'customer.email' => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:255'],
            'customer.phone' => ['required_with:customer', 'string', 'min:1', 'max:30'],
            'customerAddressId' => ['sometimes', 'integer', 'min:1'],
            'address' => ['sometimes', 'nullable', 'array'],
            'address.province' => ['required_with:address', 'string', 'min:1', 'max:150'],
            'address.city' => ['required_with:address', 'string', 'min:1', 'max:150'],
            'address.address' => ['required_with:address', 'string', 'min:1', 'max:500'],
            'customerNote' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'adminNote' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'discountType' => ['sometimes', 'nullable', 'integer', Rule::in(array_map(
                static fn (DiscountType $type) => $type->value,
                DiscountType::cases(),
            ))],
            'discountValue' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'discountReason' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'orderPlace' => ['sometimes', 'integer', Rule::in(array_map(
                static fn (OrderPlace $place) => $place->value,
                OrderPlace::cases(),
            ))],
            'status' => ['sometimes', 'integer', Rule::in(array_map(
                static fn (OrderStatus $status) => $status->value,
                OrderStatus::cases(),
            ))],
            'reason' => ['sometimes', 'nullable', 'string', 'min:1', 'max:1000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->guardUnexpectedKeys($validator);

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->all() === []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));

                    return;
                }

                $hasCustomerId = array_key_exists('customerId', $this->all());
                $hasCustomer = is_array($this->input('customer'));

                if ($hasCustomerId && $hasCustomer) {
                    $validator->errors()->add('customer', __('validation.invalid_payload'));
                }

                if ($this->filled('customerAddressId') && is_array($this->input('address'))) {
                    $validator->errors()->add('address', __('validation.invalid_payload'));
                }

                $hasDiscountTypeKey = array_key_exists('discountType', $this->all());
                $hasDiscountValueKey = array_key_exists('discountValue', $this->all());
                $hasDiscountReasonKey = array_key_exists('discountReason', $this->all());

                if (! $hasDiscountTypeKey && ($hasDiscountValueKey || $hasDiscountReasonKey)) {
                    $validator->errors()->add('discount', __('validation.invalid_payload'));
                }

                if ($hasDiscountTypeKey && $this->input('discountType') !== null) {
                    if (
                        ! $hasDiscountValueKey
                        || $this->input('discountValue') === null
                        || ! $hasDiscountReasonKey
                        || $this->normalizeOptionalString($this->input('discountReason')) === null
                    ) {
                        $validator->errors()->add('discount', __('validation.invalid_payload'));
                    }
                }

                if ($hasDiscountTypeKey && $this->input('discountType') === null) {
                    if (
                        ($hasDiscountValueKey && $this->input('discountValue') !== null)
                        || ($hasDiscountReasonKey && $this->normalizeOptionalString($this->input('discountReason')) !== null)
                    ) {
                        $validator->errors()->add('discount', __('validation.invalid_payload'));
                    }
                }

                if (! array_key_exists('status', $this->all()) && array_key_exists('reason', $this->all())) {
                    $validator->errors()->add('reason', __('validation.invalid_payload'));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $customer = $this->input('customer');
        $address = $this->input('address');

        if (is_array($customer)) {
            $customer['name'] = $this->normalizeOptionalString($customer['name'] ?? null);
            $customer['email'] = $this->normalizeOptionalEmail($customer['email'] ?? null);
            $customer['phone'] = $this->normalizeOptionalString($customer['phone'] ?? null);
        }

        if (is_array($address)) {
            $address['province'] = $this->normalizeOptionalString($address['province'] ?? null);
            $address['city'] = $this->normalizeOptionalString($address['city'] ?? null);
            $address['address'] = $this->normalizeOptionalString($address['address'] ?? null);
        }

        $payload = [
            'customerNote' => $this->normalizeOptionalString($this->input('customerNote')),
            'adminNote' => $this->normalizeOptionalString($this->input('adminNote')),
            'discountReason' => $this->normalizeOptionalString($this->input('discountReason')),
            'reason' => $this->normalizeOptionalString($this->input('reason')),
        ];

        if (is_array($customer)) {
            $payload['customer'] = $customer;
        }

        if (array_key_exists('address', $this->all())) {
            $payload['address'] = is_array($address) ? $address : null;
        }

        $this->merge($payload);
    }

    public function payload(): array
    {
        return $this->validated();
    }

    private function guardUnexpectedKeys(Validator $validator): void
    {
        $allowedTopLevel = [
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
            'status',
            'reason',
        ];

        if (array_diff(array_keys($this->all()), $allowedTopLevel) !== []) {
            $validator->errors()->add('payload', __('validation.invalid_payload'));
        }

        $customer = $this->input('customer');
        if (is_array($customer) && array_diff(array_keys($customer), ['name', 'email', 'phone']) !== []) {
            $validator->errors()->add('payload', __('validation.invalid_payload'));
        }

        $address = $this->input('address');
        if (is_array($address) && array_diff(array_keys($address), ['province', 'city', 'address']) !== []) {
            $validator->errors()->add('payload', __('validation.invalid_payload'));
        }
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeOptionalEmail(mixed $value): ?string
    {
        $normalized = $this->normalizeOptionalString($value);

        return $normalized === null ? null : mb_strtolower($normalized);
    }
}
