<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Services;

use Illuminate\Validation\Rule;

class UpdateServiceRequest extends StoreServiceRequest
{
    public function rules(): array
    {
        return [
            'categoryId' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'subcategoryId' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'nameAr' => ['sometimes', 'string', 'max:150'],
            'nameEn' => ['sometimes', 'string', 'max:150'],
            'shortDescriptionAr' => ['sometimes', 'string', 'max:500'],
            'shortDescriptionEn' => ['sometimes', 'string', 'max:500'],
            'descriptionAr' => ['nullable', 'string', 'max:5000', 'required_with:descriptionEn'],
            'descriptionEn' => ['nullable', 'string', 'max:5000', 'required_with:descriptionAr'],
            'slugAr' => ['sometimes', 'nullable', 'string', 'max:180'],
            'slugEn' => ['sometimes', 'nullable', 'string', 'max:180'],
            'productionTimeAr' => ['sometimes', 'nullable', 'string', 'max:255', 'required_with:productionTimeEn'],
            'productionTimeEn' => ['sometimes', 'nullable', 'string', 'max:255', 'required_with:productionTimeAr'],
            'priceType' => ['sometimes', 'integer', Rule::in([0, 1])],
            'basePrice' => ['sometimes', 'numeric', 'gt:0'],
            'isActive' => ['sometimes', 'boolean'],
            'isAvailable' => ['sometimes', 'boolean'],
            'isAttachmentRequired' => ['sometimes', 'boolean'],
            'seoTitleAr' => ['sometimes', 'nullable', 'string', 'max:70', 'required_with:seoTitleEn'],
            'seoTitleEn' => ['sometimes', 'nullable', 'string', 'max:70', 'required_with:seoTitleAr'],
            'seoDescriptionAr' => ['sometimes', 'nullable', 'string', 'max:180', 'required_with:seoDescriptionEn'],
            'seoDescriptionEn' => ['sometimes', 'nullable', 'string', 'max:180', 'required_with:seoDescriptionAr'],
            'seoTagsAr' => ['sometimes', 'nullable', 'array', 'max:20'],
            'seoTagsAr.*' => ['string', 'max:70'],
            'seoTagsEn' => ['sometimes', 'nullable', 'array', 'max:20'],
            'seoTagsEn.*' => ['string', 'max:70'],
        ];
    }
}
