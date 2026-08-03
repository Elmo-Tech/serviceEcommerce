<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Categories;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class AbstractCategoryPayloadRequest extends FormRequest
{
    public function messages(): array
    {
        return [
            'nameAr.unique' => __('categories.validation.name_ar_unique'),
            'nameEn.unique' => __('categories.validation.name_en_unique'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = $this->all();

        if (array_key_exists('isActive', $normalized) && $normalized['isActive'] === 'true') {
            $normalized['isActive'] = true;
        }

        if (array_key_exists('isActive', $normalized) && $normalized['isActive'] === 'false') {
            $normalized['isActive'] = false;
        }

        foreach (['nameAr', 'nameEn', 'slugAr', 'slugEn'] as $field) {
            if (! array_key_exists($field, $normalized) || ! is_string($normalized[$field])) {
                continue;
            }

            $normalized[$field] = trim($normalized[$field]);
        }

        foreach (['descriptionAr', 'descriptionEn'] as $field) {
            if (! array_key_exists($field, $normalized)) {
                continue;
            }

            if ($normalized[$field] === null) {
                continue;
            }

            if (is_string($normalized[$field])) {
                $normalized[$field] = trim($normalized[$field]);
            }
        }

        $this->replace($normalized);
    }

    protected function validateAllowedKeys(Validator $validator, array $allowedKeys): void
    {
        $unexpectedKeys = array_diff(array_keys($this->all()), $allowedKeys);

        if ($unexpectedKeys !== []) {
            $validator->errors()->add('payload', __('validation.invalid_payload'));
        }
    }

    protected function validateDescriptionPair(Validator $validator): void
    {
        $hasDescriptionAr = $this->has('descriptionAr');
        $hasDescriptionEn = $this->has('descriptionEn');

        if ($hasDescriptionAr xor $hasDescriptionEn) {
            $validator->errors()->add('payload', __('validation.invalid_payload'));

            return;
        }

        if (! $hasDescriptionAr && ! $hasDescriptionEn) {
            return;
        }

        $descriptionAr = $this->normalizeNullableText($this->input('descriptionAr'));
        $descriptionEn = $this->normalizeNullableText($this->input('descriptionEn'));

        $bothNull = $descriptionAr === null && $descriptionEn === null;
        $bothText = $descriptionAr !== null && $descriptionEn !== null;

        if (! $bothNull && ! $bothText) {
            $validator->errors()->add('payload', __('validation.invalid_payload'));
        }
    }

    protected function validateNonEmptyUpdatePayload(Validator $validator, array $allowedKeys): void
    {
        if (array_intersect(array_keys($this->all()), $allowedKeys) === []) {
            $validator->errors()->add('payload', __('validation.invalid_payload'));
        }
    }

    protected function validateUpdateSlugFields(Validator $validator): void
    {
        foreach (['slugAr', 'slugEn'] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);

            if (! is_string($value) || trim($value) === '') {
                $validator->errors()->add($field, __('validation.invalid_payload'));
            }
        }
    }

    protected function normalizeNullableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function normalizedDescriptionPair(): array
    {
        return [
            'descriptionAr' => $this->normalizeNullableText($this->input('descriptionAr')),
            'descriptionEn' => $this->normalizeNullableText($this->input('descriptionEn')),
        ];
    }
}
