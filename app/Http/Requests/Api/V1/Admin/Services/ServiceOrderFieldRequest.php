<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Services;

use Illuminate\Foundation\Http\FormRequest;

class ServiceOrderFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'labelAr' => ['required', 'string', 'max:150'],
            'labelEn' => ['required', 'string', 'max:150'],
            'isRequired' => ['required', 'boolean'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
