<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\HeroSlides;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreHeroSlideRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $normalized = $this->all();

        foreach (['titleAr', 'titleEn', 'descriptionAr', 'descriptionEn'] as $field) {
            if (isset($normalized[$field]) && is_string($normalized[$field])) {
                $normalized[$field] = trim($normalized[$field]);
            }
        }

        foreach (['isActive', 'position'] as $field) {
            if (isset($normalized[$field]) && is_int($normalized[$field])) {
                $normalized[$field] = (string) $normalized[$field];
            }
        }

        $this->merge($normalized);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titleAr' => ['required', 'string', 'min:1', 'max:150', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'titleEn' => ['required', 'string', 'min:1', 'max:150', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'descriptionAr' => ['required', 'string', 'min:1', 'max:1000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'descriptionEn' => ['required', 'string', 'min:1', 'max:1000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'image' => ['required', 'file', 'max:5120', 'extensions:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp'],
            'isActive' => ['required', 'string', 'regex:/^[01]$/'],
            'position' => ['sometimes', 'string', 'regex:/^[1-9][0-9]*$/', Rule::in(array_map('strval', range(1, 10)))],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $unexpected = array_diff(array_keys($this->all()), [
                    'titleAr',
                    'titleEn',
                    'descriptionAr',
                    'descriptionEn',
                    'image',
                    'isActive',
                    'position',
                ]);

                if ($unexpected !== []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();
        $validated['isActive'] = (int) $validated['isActive'];

        if (array_key_exists('position', $validated)) {
            $validated['position'] = (int) $validated['position'];
        }

        return $validated;
    }
}
