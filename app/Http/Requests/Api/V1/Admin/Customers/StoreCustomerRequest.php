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
        $address = $this->input('address');
        $payload = [
            'email' => $this->has('email') ? trim((string) $this->input('email')) : null,
            'phoneCountryCode' => $this->filled('phoneCountryCode')
                ? strtoupper(trim((string) $this->input('phoneCountryCode')))
                : 'EG',
        ];

        if (is_array($address)) {
            $payload['address'] = [
                'phone' => array_key_exists('phone', $address)
                    ? trim((string) $address['phone'])
                    : null,
                'province' => array_key_exists('province', $address)
                    ? trim((string) $address['province'])
                    : null,
                'city' => array_key_exists('city', $address)
                    ? trim((string) $address['city'])
                    : null,
                'address' => array_key_exists('address', $address)
                    ? trim((string) $address['address'])
                    : null,
                'notes' => array_key_exists('notes', $address)
                    ? trim((string) $address['notes'])
                    : null,
            ];
        }

        $this->merge($payload);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:150'],
            'email' => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'min:1', 'max:30'],
            'phoneCountryCode' => ['sometimes', 'string', 'size:2', 'alpha'],
            'address' => ['sometimes', 'array'],
            'address.phone' => ['required_with:address', 'string', 'min:1', 'max:30'],
            'address.province' => ['required_with:address', 'string', 'min:1', 'max:150'],
            'address.city' => ['required_with:address', 'string', 'min:1', 'max:150'],
            'address.address' => ['required_with:address', 'string', 'min:1', 'max:500'],
            'address.notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowedKeys = ['name', 'email', 'phone', 'phoneCountryCode', 'address'];
                $unexpectedKeys = array_diff(array_keys($this->all()), $allowedKeys);

                if ($unexpectedKeys !== []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }

                $address = $this->input('address');

                if (is_array($address)) {
                    $allowedAddressKeys = ['phone', 'province', 'city', 'address', 'notes'];
                    $unexpectedAddressKeys = array_diff(array_keys($address), $allowedAddressKeys);

                    if ($unexpectedAddressKeys !== []) {
                        $validator->errors()->add('payload', __('validation.invalid_payload'));
                    }
                }
            },
        ];
    }
}
