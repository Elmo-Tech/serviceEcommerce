<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Faqs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ListFaqsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.isActive' => ['sometimes', 'string', 'regex:/^[01]$/'],
            'filter.search' => ['sometimes', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $unexpectedKeys = array_diff(array_keys($this->query()), ['filter', 'page', 'perPage']);

                if ($unexpectedKeys !== []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }

                $filter = $this->query('filter', []);

                if (is_array($filter)) {
                    $unexpectedFilterKeys = array_diff(array_keys($filter), ['isActive', 'search']);

                    if ($unexpectedFilterKeys !== []) {
                        $validator->errors()->add('payload', __('validation.invalid_payload'));
                    }
                }
            },
        ];
    }

    /**
     * @return array{page:int,perPage:int,isActive:int|null,search:?string}
     */
    public function filters(): array
    {
        $filter = $this->query('filter', []);

        return [
            'page' => (int) $this->input('page', 1),
            'perPage' => (int) $this->input('perPage', 15),
            'isActive' => is_array($filter) && array_key_exists('isActive', $filter)
                ? (int) $filter['isActive']
                : null,
            'search' => $this->filled('filter.search')
                ? trim((string) $this->input('filter.search'))
                : null,
        ];
    }
}
