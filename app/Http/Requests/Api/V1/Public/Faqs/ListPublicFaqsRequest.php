<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Public\Faqs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ListPublicFaqsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $unexpectedKeys = array_diff(array_keys($this->query()), ['page', 'perPage']);

                if ($unexpectedKeys !== []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }
            },
        ];
    }

    /**
     * @return array{page:int,perPage:int}
     */
    public function filters(): array
    {
        return [
            'page' => (int) $this->input('page', 1),
            'perPage' => (int) $this->input('perPage', 15),
        ];
    }
}
