<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Services;

use Illuminate\Validation\Rule;

class UpdateServicePricingOptionRequest extends ServicePricingOptionRequest
{
    protected function prepareForValidation(): void
    {
        $values = $this->input('values');

        if (is_array($values)) {
            foreach ($values as $index => $value) {
                if (is_array($value) && array_key_exists('actionStatus', $value) && $value['actionStatus'] === null) {
                    $values[$index]['actionStatus'] = '';
                }
            }

            $this->merge(['values' => $values]);
        }
    }

    public function rules(): array
    {
        return [
            'nameAr' => ['sometimes', 'string', 'max:150'],
            'nameEn' => ['sometimes', 'string', 'max:150'],
            'inputType' => ['sometimes', 'integer', Rule::in([0, 1, 2, 3])],
            'isRequired' => ['sometimes', 'boolean'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
            'values' => ['sometimes', 'array', 'max:30'],
            'values.*.id' => ['sometimes', 'integer', 'min:1'],
            'values.*.actionStatus' => ['present', 'string', Rule::in(['', 'create', 'update', 'delete'])],
            'values.*.labelAr' => ['sometimes', 'string', 'max:150'],
            'values.*.labelEn' => ['sometimes', 'string', 'max:150'],
            'values.*.priceAdjustment' => ['sometimes', 'numeric', 'min:0'],
            'values.*.isActive' => ['sometimes', 'boolean'],
            'values.*.sortOrder' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
