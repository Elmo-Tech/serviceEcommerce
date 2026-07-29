<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'label' => $this->normalizeOptional('label'),
            'area' => $this->normalizeOptional('area'),
            'notes' => $this->normalizeOptional('notes'),
            'phoneCountryCode' => $this->filled('phoneCountryCode')
                ? strtoupper(trim((string) $this->input('phoneCountryCode')))
                : 'EG',
            'countryCode' => strtoupper(trim((string) $this->input('countryCode'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'min:1', 'max:30'],
            'phoneCountryCode' => ['sometimes', 'string', 'size:2', 'alpha'],
            'countryCode' => ['required', 'string', 'size:2', 'alpha'],
            'city' => ['required', 'string', 'min:1', 'max:150'],
            'area' => ['sometimes', 'nullable', 'string', 'max:150'],
            'street' => ['required', 'string', 'min:1', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'isDefault' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowedKeys = ['label', 'phone', 'phoneCountryCode', 'countryCode', 'city', 'area', 'street', 'notes', 'isDefault'];
                $unexpectedKeys = array_diff(array_keys($this->all()), $allowedKeys);

                if ($unexpectedKeys !== []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }
            },
        ];
    }

    private function normalizeOptional(string $key): ?string
    {
        if (! $this->has($key)) {
            return null;
        }

        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
