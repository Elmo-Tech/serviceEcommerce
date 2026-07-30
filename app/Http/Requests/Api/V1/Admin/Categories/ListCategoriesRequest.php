<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Categories;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListCategoriesRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $query = $this->query();
        $filter = $query['filter'] ?? [];

        if (is_array($filter) && array_key_exists('isActive', $filter)) {
            $filter['isActive'] = filter_var(
                $filter['isActive'],
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
            'filter.isActive' => ['sometimes', 'boolean'],
            'filter.trashed' => ['sometimes', 'string', Rule::in(['without', 'with', 'only'])],
            'sort' => ['sometimes', 'string', Rule::in([
                'sortOrder',
                '-sortOrder',
                'name',
                '-name',
                'createdAt',
                '-createdAt',
                'updatedAt',
                '-updatedAt',
            ])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $unexpectedKeys = array_diff(array_keys($this->query()), ['filter', 'sort', 'page', 'perPage']);

                if ($unexpectedKeys !== []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }

                $filter = $this->query('filter', []);

                if (is_array($filter)) {
                    $unexpectedFilterKeys = array_diff(array_keys($filter), ['search', 'isActive', 'trashed']);

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
            'isActive' => $this->has('filter.isActive') ? $this->boolean('filter.isActive') : null,
            'trashed' => (string) $this->input('filter.trashed', 'without'),
            'sort' => (string) $this->input('sort', 'sortOrder'),
            'page' => (int) $this->input('page', 1),
            'perPage' => (int) $this->input('perPage', 20),
        ];
    }
}
