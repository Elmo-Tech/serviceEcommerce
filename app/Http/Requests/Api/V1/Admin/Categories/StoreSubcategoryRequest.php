<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Categories;

use Illuminate\Validation\Validator;

class StoreSubcategoryRequest extends StoreCategoryRequest
{
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if ($this->has('parentId') || $this->has('categoryId')) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }
            },
        ];
    }
}
