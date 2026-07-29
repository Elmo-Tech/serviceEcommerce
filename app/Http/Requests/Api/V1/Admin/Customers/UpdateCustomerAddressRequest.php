<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['label', 'area', 'notes'] as $key) {
            if ($this->has($key)) {
                $value = trim((string) $this->input($key));
                $this->merge([$key => $value === '' ? null : $value]);
            }
        }

        if ($this->filled('phoneCountryCode')) {
            $this->merge([
                'phoneCountryCode' => strtoupper(trim((string) $this->input('phoneCountryCode'))),
            ]);
        }

        if ($this->filled('countryCode')) {
            $this->merge([
                'countryCode' => strtoupper(trim((string) $this->input('countryCode'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone' => ['sometimes', 'required', 'string', 'min:1', 'max:30'],
            'phoneCountryCode' => ['sometimes', 'string', 'size:2', 'alpha'],
            'countryCode' => ['sometimes', 'string', 'size:2', 'alpha'],
            'city' => ['sometimes', 'required', 'string', 'min:1', 'max:150'],
            'area' => ['sometimes', 'nullable', 'string', 'max:150'],
            'street' => ['sometimes', 'required', 'string', 'min:1', 'max:255'],
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

                if (array_intersect(array_keys($this->all()), $allowedKeys) === []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }

                if ($this->filled('phoneCountryCode') && ! $this->has('phone')) {
                    $validator->errors()->add('phone', __('validation.invalid_payload'));
                }
            },
        ];
    }
}
