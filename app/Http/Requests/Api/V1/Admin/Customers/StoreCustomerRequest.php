<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->has('email') ? trim((string) $this->input('email')) : null,
            'phoneCountryCode' => $this->filled('phoneCountryCode')
                ? strtoupper(trim((string) $this->input('phoneCountryCode')))
                : 'EG',
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:150'],
            'email' => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'min:1', 'max:30'],
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
            },
        ];
    }
}
