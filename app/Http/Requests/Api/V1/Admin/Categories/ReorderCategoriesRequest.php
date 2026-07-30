<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Categories;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReorderCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'orderedIds' => ['required', 'array', 'min:1'],
            'orderedIds.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $unexpectedKeys = array_diff(array_keys($this->all()), ['orderedIds']);

                if ($unexpectedKeys !== []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }
            },
        ];
    }

    /**
     * @return list<int>
     */
    public function orderedIds(): array
    {
        return array_map('intval', (array) $this->input('orderedIds', []));
    }
}
