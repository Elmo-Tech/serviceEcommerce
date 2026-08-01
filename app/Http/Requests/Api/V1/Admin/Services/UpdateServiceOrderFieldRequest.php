<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Services;

class UpdateServiceOrderFieldRequest extends ServiceOrderFieldRequest
{
    public function rules(): array
    {
        return [
            'labelAr' => ['sometimes', 'string', 'max:150'],
            'labelEn' => ['sometimes', 'string', 'max:150'],
            'isRequired' => ['sometimes', 'boolean'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
