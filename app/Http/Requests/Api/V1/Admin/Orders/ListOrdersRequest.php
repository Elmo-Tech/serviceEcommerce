<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Orders;

use App\Enums\Orders\OrderPlace;
use App\Enums\Orders\OrderStatus;
use App\Enums\Orders\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'filter.status' => ['sometimes', 'integer', Rule::in(array_map(
                static fn (OrderStatus $status) => $status->value,
                OrderStatus::cases(),
            ))],
            'filter.paymentStatus' => ['sometimes', 'integer', Rule::in(array_map(
                static fn (PaymentStatus $status) => $status->value,
                PaymentStatus::cases(),
            ))],
            'filter.orderPlace' => ['sometimes', 'integer', Rule::in(array_map(
                static fn (OrderPlace $place) => $place->value,
                OrderPlace::cases(),
            ))],
            'filter.customerId' => ['sometimes', 'integer', 'min:1'],
            'filter.serviceId' => ['sometimes', 'integer', 'min:1'],
            'filter.createdFrom' => ['sometimes', 'date'],
            'filter.createdTo' => ['sometimes', 'date'],
            'filter.totalFrom' => ['sometimes', 'numeric', 'min:0'],
            'filter.totalTo' => ['sometimes', 'numeric', 'min:0'],
            'sort' => ['sometimes', 'string', Rule::in(['createdAt', '-createdAt', 'total', '-total'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $filter = $this->input('filter', []);

        if (is_array($filter)) {
            $normalizedFilter = [];

            foreach ($filter as $key => $value) {
                $normalizedFilter[$key] = $value;
            }

            foreach (['search', 'createdFrom', 'createdTo'] as $key) {
                if (array_key_exists($key, $normalizedFilter)) {
                    $normalizedValue = $this->normalizeOptionalString($normalizedFilter[$key]);

                    if ($normalizedValue === null) {
                        unset($normalizedFilter[$key]);
                    } else {
                        $normalizedFilter[$key] = $normalizedValue;
                    }
                }
            }

            $filter = $normalizedFilter;
        }

        $this->merge([
            'filter' => $filter,
            'sort' => $this->normalizeOptionalString($this->input('sort')) ?? '-createdAt',
        ]);
    }

    public function filters(): array
    {
        return [
            ...(is_array($this->validated('filter')) ? $this->validated('filter') : []),
            'sort' => (string) $this->validated('sort', '-createdAt'),
            'page' => (int) $this->validated('page', 1),
            'perPage' => (int) $this->validated('perPage', 15),
        ];
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
