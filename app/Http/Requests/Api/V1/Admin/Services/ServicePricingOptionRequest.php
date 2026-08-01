<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Services;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServicePricingOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nameAr' => ['required', 'string', 'max:150'],
            'nameEn' => ['required', 'string', 'max:150'],
            'inputType' => ['required', 'integer', Rule::in([0, 1, 2, 3])],
            'isRequired' => ['required', 'boolean'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
            'values' => ['sometimes', 'array', 'max:30'],
            'values.*.labelAr' => ['required_with:values', 'string', 'max:150'],
            'values.*.labelEn' => ['required_with:values', 'string', 'max:150'],
            'values.*.priceAdjustment' => ['required_with:values', 'numeric', 'min:0'],
            'values.*.isActive' => ['required_with:values', 'boolean'],
            'values.*.sortOrder' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
