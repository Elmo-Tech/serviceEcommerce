<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Services;

class UpdateServiceSpecificationRequest extends ServiceSpecificationRequest
{
    public function rules(): array
    {
        return [
            'labelAr' => ['sometimes', 'string', 'max:150'],
            'labelEn' => ['sometimes', 'string', 'max:150'],
            'valueAr' => ['sometimes', 'string', 'max:1000'],
            'valueEn' => ['sometimes', 'string', 'max:1000'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
