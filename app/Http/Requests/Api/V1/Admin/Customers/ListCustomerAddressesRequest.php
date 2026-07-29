<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListCustomerAddressesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.status' => ['sometimes', 'string', Rule::in(['active', 'deleted', 'all'])],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $unexpectedKeys = array_diff(array_keys($this->query()), ['filter']);

                if ($unexpectedKeys !== []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }

                $filter = $this->query('filter', []);

                if (is_array($filter)) {
                    $unexpectedFilterKeys = array_diff(array_keys($filter), ['status']);

                    if ($unexpectedFilterKeys !== []) {
                        $validator->errors()->add('payload', __('validation.invalid_payload'));
                    }
                }
            },
        ];
    }

    public function statusFilter(): string
    {
        return (string) $this->input('filter.status', 'active');
    }
}
