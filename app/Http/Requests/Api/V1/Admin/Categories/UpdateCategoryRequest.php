<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Categories;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends AbstractCategoryPayloadRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = (int) $this->route('category');

        return [
            'nameAr' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
            'nameEn' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
            'descriptionAr' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'descriptionEn' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'slugAr' => ['sometimes', 'required', 'string', 'max:180', Rule::unique('categories', 'slug_ar')->ignore($categoryId)],
            'slugEn' => ['sometimes', 'required', 'string', 'max:180', Rule::unique('categories', 'slug_en')->ignore($categoryId)],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
            'isActive' => ['sometimes', 'boolean'],
            'image' => ['sometimes', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowedKeys = [
                    'nameAr',
                    'nameEn',
                    'descriptionAr',
                    'descriptionEn',
                    'slugAr',
                    'slugEn',
                    'sortOrder',
                    'isActive',
                    'image',
                ];

                $this->validateAllowedKeys($validator, $allowedKeys);
                $this->validateNonEmptyUpdatePayload($validator, $allowedKeys);
                $this->validateDescriptionPair($validator);
                $this->validateUpdateSlugFields($validator);
            },
        ];
    }

    public function payload(): array
    {
        $payload = [];

        foreach (['nameAr', 'nameEn'] as $field) {
            if ($this->has($field)) {
                $payload[$field] = trim((string) $this->input($field));
            }
        }

        if ($this->has('descriptionAr') || $this->has('descriptionEn')) {
            $payload = [...$payload, ...$this->normalizedDescriptionPair()];
        }

        foreach (['slugAr', 'slugEn'] as $field) {
            if ($this->has($field)) {
                $payload[$field] = trim((string) $this->input($field));
            }
        }

        if ($this->has('sortOrder')) {
            $payload['sortOrder'] = (int) $this->input('sortOrder');
        }

        if ($this->has('isActive')) {
            $payload['isActive'] = $this->boolean('isActive');
        }

        if ($this->hasFile('image')) {
            $payload['image'] = $this->file('image');
        }

        return $payload;
    }
}
