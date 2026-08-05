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
            'province' => $this->normalizeOptional('province'),
            'city' => $this->normalizeOptional('city'),
            'address' => $this->normalizeOptional('address'),
            'notes' => $this->normalizeOptional('notes'),
        ]);
    }

    public function rules(): array
    {
        return [
            'province' => ['required', 'string', 'min:1', 'max:150'],
            'city' => ['required', 'string', 'min:1', 'max:150'],
            'address' => ['required', 'string', 'min:1', 'max:500'],
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
                $allowedKeys = ['province', 'city', 'address', 'notes', 'isDefault'];
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
