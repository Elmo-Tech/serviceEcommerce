<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Categories;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends AbstractCategoryPayloadRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nameAr' => ['required', 'string', 'min:2', 'max:150'],
            'nameEn' => ['required', 'string', 'min:2', 'max:150'],
            'descriptionAr' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'descriptionEn' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'slugAr' => ['sometimes', 'nullable', 'string', 'max:180', Rule::unique('categories', 'slug_ar')],
            'slugEn' => ['sometimes', 'nullable', 'string', 'max:180', Rule::unique('categories', 'slug_en')],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateAllowedKeys($validator, [
                    'nameAr',
                    'nameEn',
                    'descriptionAr',
                    'descriptionEn',
                    'slugAr',
                    'slugEn',
                    'sortOrder',
                    'isActive',
                ]);

                $this->validateDescriptionPair($validator);
            },
        ];
    }

    public function payload(): array
    {
        $payload = [
            'nameAr' => trim((string) $this->input('nameAr')),
            'nameEn' => trim((string) $this->input('nameEn')),
            'sortOrder' => (int) $this->input('sortOrder', 0),
            'isActive' => $this->has('isActive') ? $this->boolean('isActive') : true,
        ];

        if ($this->has('descriptionAr') || $this->has('descriptionEn')) {
            $payload = [...$payload, ...$this->normalizedDescriptionPair()];
        }

        if ($this->filled('slugAr')) {
            $payload['slugAr'] = trim((string) $this->input('slugAr'));
        }

        if ($this->filled('slugEn')) {
            $payload['slugEn'] = trim((string) $this->input('slugEn'));
        }

        return $payload;
    }
}
