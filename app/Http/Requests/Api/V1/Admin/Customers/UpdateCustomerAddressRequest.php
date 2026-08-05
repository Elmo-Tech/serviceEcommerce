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
        foreach (['province', 'city', 'address', 'notes'] as $key) {
            if ($this->has($key)) {
                $value = trim((string) $this->input($key));
                $this->merge([$key => $value === '' ? null : $value]);
            }
        }

    }

    public function rules(): array
    {
        return [
            'province' => ['sometimes', 'required', 'string', 'min:1', 'max:150'],
            'city' => ['sometimes', 'required', 'string', 'min:1', 'max:150'],
            'address' => ['sometimes', 'required', 'string', 'min:1', 'max:500'],
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

                if (array_intersect(array_keys($this->all()), $allowedKeys) === []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }

            },
        ];
    }
}
