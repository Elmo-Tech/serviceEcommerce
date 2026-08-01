<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Services;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListServicesRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $query = $this->query();
        $filter = $query['filter'] ?? [];

        foreach (['isActive', 'isAvailable'] as $key) {
            if (is_array($filter) && array_key_exists($key, $filter)) {
                $filter[$key] = filter_var($filter[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
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
            'filter.categoryId' => ['sometimes', 'integer', 'min:1'],
            'filter.subcategoryId' => ['sometimes', 'integer', 'min:1'],
            'filter.priceType' => ['sometimes', 'integer', Rule::in([0, 1])],
            'filter.isActive' => ['sometimes', 'boolean'],
            'filter.isAvailable' => ['sometimes', 'boolean'],
            'filter.trashed' => ['sometimes', 'string', Rule::in(['without', 'with', 'only'])],
            'sort' => ['sometimes', 'string', Rule::in(['basePrice', '-basePrice', 'createdAt', '-createdAt'])],
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
                $allowedKeys = ['filter', 'sort', 'page', 'perPage'];
                $unexpectedKeys = array_diff(array_keys($this->query()), $allowedKeys);

                if ($unexpectedKeys !== []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }

                $filter = $this->query('filter', []);

                if (is_array($filter)) {
                    $allowedFilterKeys = [
                        'search',
                        'categoryId',
                        'subcategoryId',
                        'priceType',
                        'isActive',
                        'isAvailable',
                        'trashed',
                    ];

                    $unexpectedFilterKeys = array_diff(array_keys($filter), $allowedFilterKeys);

                    if ($unexpectedFilterKeys !== []) {
                        $validator->errors()->add('payload', __('validation.invalid_payload'));
                    }
                }
            },
        ];
    }

    public function filters(): array
    {
        return [
            'search' => $this->filled('filter.search') ? trim((string) $this->input('filter.search')) : null,
            'categoryId' => $this->input('filter.categoryId'),
            'subcategoryId' => $this->input('filter.subcategoryId'),
            'priceType' => $this->input('filter.priceType'),
            'isActive' => $this->has('filter.isActive') ? $this->boolean('filter.isActive') : null,
            'isAvailable' => $this->has('filter.isAvailable') ? $this->boolean('filter.isAvailable') : null,
            'trashed' => (string) $this->input('filter.trashed', 'without'),
            'sort' => (string) $this->input('sort', '-createdAt'),
            'page' => (int) $this->input('page', 1),
            'perPage' => (int) $this->input('perPage', 20),
        ];
    }
}
