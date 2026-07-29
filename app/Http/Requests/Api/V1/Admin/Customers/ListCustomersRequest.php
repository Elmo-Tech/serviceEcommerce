<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListCustomersRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $query = $this->query();
        $filter = $query['filter'] ?? [];

        if (is_array($filter) && array_key_exists('hasAddresses', $filter)) {
            $filter['hasAddresses'] = filter_var(
                $filter['hasAddresses'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            );
        }

        if ($filter !== []) {
            $query['filter'] = $filter;
        }

        $this->merge($query);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.search' => ['sometimes', 'string', 'max:255'],
            'filter.status' => ['sometimes', 'string', Rule::in(['active', 'deleted', 'all'])],
            'filter.hasAddresses' => ['sometimes', 'boolean'],
            'filter.createdFrom' => ['sometimes', 'date'],
            'filter.createdTo' => ['sometimes', 'date'],
            'sort' => ['sometimes', 'string', Rule::in(['createdAt', '-createdAt', 'name', '-name'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowedKeys = [
                    'filter',
                    'sort',
                    'page',
                    'perPage',
                ];

                $unexpectedKeys = array_diff(array_keys($this->query()), $allowedKeys);

                if ($unexpectedKeys !== []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }

                $filter = $this->query('filter', []);

                if (is_array($filter)) {
                    $allowedFilterKeys = [
                        'search',
                        'status',
                        'hasAddresses',
                        'createdFrom',
                        'createdTo',
                    ];

                    $unexpectedFilterKeys = array_diff(array_keys($filter), $allowedFilterKeys);

                    if ($unexpectedFilterKeys !== []) {
                        $validator->errors()->add('payload', __('validation.invalid_payload'));
                    }
                }

                if (
                    $this->filled('filter.createdFrom')
                    && $this->filled('filter.createdTo')
                    && (string) $this->input('filter.createdFrom') > (string) $this->input('filter.createdTo')
                ) {
                    $validator->errors()->add('filter.createdTo', __('validation.invalid_payload'));
                }
            },
        ];
    }

    public function filters(): array
    {
        return [
            'search' => $this->filled('filter.search') ? trim((string) $this->input('filter.search')) : null,
            'status' => (string) $this->input('filter.status', 'active'),
            'hasAddresses' => $this->has('filter.hasAddresses') ? $this->boolean('filter.hasAddresses') : null,
            'createdFrom' => $this->input('filter.createdFrom'),
            'createdTo' => $this->input('filter.createdTo'),
            'sort' => (string) $this->input('sort', '-createdAt'),
            'page' => (int) $this->input('page', 1),
            'perPage' => (int) $this->input('perPage', 20),
        ];
    }
}
