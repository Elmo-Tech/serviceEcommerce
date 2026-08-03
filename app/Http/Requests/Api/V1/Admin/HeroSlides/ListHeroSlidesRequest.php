<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\HeroSlides;

use App\Services\HeroSlides\HeroSlideQueryShapeGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ListHeroSlidesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.isActive' => ['sometimes', 'string', 'regex:/^[01]$/'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (app(HeroSlideQueryShapeGuard::class)->violations(
                    $this->server('QUERY_STRING'),
                ) as $attribute => $translationKey) {
                    $validator->errors()->add($attribute, __($translationKey));
                }
            },
        ];
    }

    /**
     * @return array{page:int,perPage:int,isActive:int|null}
     */
    public function filters(): array
    {
        $filter = $this->validated('filter', []);

        return [
            'page' => (int) $this->validated('page', 1),
            'perPage' => (int) $this->validated('perPage', 15),
            'isActive' => is_array($filter) && array_key_exists('isActive', $filter)
                ? (int) $filter['isActive']
                : null,
        ];
    }
}
