<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => $this->input('email') === null ? null : trim((string) $this->input('email')),
            ]);
        }

        if ($this->filled('phoneCountryCode')) {
            $this->merge([
                'phoneCountryCode' => strtoupper(trim((string) $this->input('phoneCountryCode'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:1', 'max:150'],
            'email' => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'min:1', 'max:30'],
            'phoneCountryCode' => ['sometimes', 'string', 'size:2', 'alpha'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowedKeys = ['name', 'email', 'phone', 'phoneCountryCode'];
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
