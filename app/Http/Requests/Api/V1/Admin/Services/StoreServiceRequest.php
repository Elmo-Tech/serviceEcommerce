<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Services;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizeBooleanLikeValues($this->all()));
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->baseRules(required: true);
    }

    public function payload(): array
    {
        return $this->validated();
    }

    protected function baseRules(bool $required): array
    {
        $requiredRule = $required ? ['required'] : ['sometimes'];

        return [
            'categoryId' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'subcategoryId' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'nameAr' => [...$requiredRule, 'string', 'max:150'],
            'nameEn' => [...$requiredRule, 'string', 'max:150'],
            'shortDescriptionAr' => [...$requiredRule, 'string', 'max:500'],
            'shortDescriptionEn' => [...$requiredRule, 'string', 'max:500'],
            'descriptionAr' => [...$requiredRule, 'string', 'max:5000'],
            'descriptionEn' => [...$requiredRule, 'string', 'max:5000'],
            'slugAr' => ['sometimes', 'nullable', 'string', 'max:180'],
            'slugEn' => ['sometimes', 'nullable', 'string', 'max:180'],
            'productionTimeAr' => ['sometimes', 'nullable', 'string', 'max:255', 'required_with:productionTimeEn'],
            'productionTimeEn' => ['sometimes', 'nullable', 'string', 'max:255', 'required_with:productionTimeAr'],
            'priceType' => [...$requiredRule, 'integer', Rule::in([0, 1])],
            'basePrice' => [...$requiredRule, 'numeric', 'gt:0'],
            'isActive' => ['sometimes', 'boolean'],
            'isAvailable' => ['sometimes', 'boolean'],
            'seoTitleAr' => ['sometimes', 'nullable', 'string', 'max:70', 'required_with:seoTitleEn'],
            'seoTitleEn' => ['sometimes', 'nullable', 'string', 'max:70', 'required_with:seoTitleAr'],
            'seoDescriptionAr' => ['sometimes', 'nullable', 'string', 'max:180', 'required_with:seoDescriptionEn'],
            'seoDescriptionEn' => ['sometimes', 'nullable', 'string', 'max:180', 'required_with:seoDescriptionAr'],
            'seoTagsAr' => ['sometimes', 'nullable', 'array', 'max:20'],
            'seoTagsAr.*' => ['string', 'max:70'],
            'seoTagsEn' => ['sometimes', 'nullable', 'array', 'max:20'],
            'seoTagsEn.*' => ['string', 'max:70'],
            'specifications' => ['sometimes', 'array', 'max:30'],
            'specifications.*.labelAr' => ['required_with:specifications', 'string', 'max:150'],
            'specifications.*.labelEn' => ['required_with:specifications', 'string', 'max:150'],
            'specifications.*.valueAr' => ['required_with:specifications', 'string', 'max:1000'],
            'specifications.*.valueEn' => ['required_with:specifications', 'string', 'max:1000'],
            'specifications.*.sortOrder' => ['sometimes', 'integer', 'min:0'],
            'orderFields' => ['sometimes', 'array', 'max:20'],
            'orderFields.*.labelAr' => ['required_with:orderFields', 'string', 'max:150'],
            'orderFields.*.labelEn' => ['required_with:orderFields', 'string', 'max:150'],
            'orderFields.*.isRequired' => ['required_with:orderFields', 'boolean'],
            'orderFields.*.sortOrder' => ['sometimes', 'integer', 'min:0'],
            'pricingOptions' => ['sometimes', 'array', 'max:10'],
            'pricingOptions.*.nameAr' => ['required_with:pricingOptions', 'string', 'max:150'],
            'pricingOptions.*.nameEn' => ['required_with:pricingOptions', 'string', 'max:150'],
            'pricingOptions.*.inputType' => ['required_with:pricingOptions', 'integer', Rule::in([0, 1, 2, 3])],
            'pricingOptions.*.isRequired' => ['required_with:pricingOptions', 'boolean'],
            'pricingOptions.*.sortOrder' => ['sometimes', 'integer', 'min:0'],
            'pricingOptions.*.values' => ['sometimes', 'array', 'max:30'],
            'pricingOptions.*.values.*.labelAr' => ['required_with:pricingOptions.*.values', 'string', 'max:150'],
            'pricingOptions.*.values.*.labelEn' => ['required_with:pricingOptions.*.values', 'string', 'max:150'],
            'pricingOptions.*.values.*.priceAdjustment' => ['required_with:pricingOptions.*.values', 'numeric', 'min:0'],
            'pricingOptions.*.values.*.isActive' => ['required_with:pricingOptions.*.values', 'boolean'],
            'pricingOptions.*.values.*.sortOrder' => ['sometimes', 'integer', 'min:0'],
            'media' => ['sometimes', 'array', 'max:11'],
            'media.*.file' => ['required_with:media', 'file', 'max:25600', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime'],
            'media.*.type' => ['required_with:media', 'integer', Rule::in([0, 1])],
            'media.*.isMain' => ['sometimes', 'boolean'],
            'media.*.altAr' => ['sometimes', 'nullable', 'string', 'max:255', 'required_with:media.*.altEn'],
            'media.*.altEn' => ['sometimes', 'nullable', 'string', 'max:255', 'required_with:media.*.altAr'],
        ];
    }

    /**
     * @param  array<string|int, mixed>  $payload
     * @return array<string|int, mixed>
     */
    protected function normalizeBooleanLikeValues(array $payload): array
    {
        $booleanKeys = [
            'isActive',
            'isAvailable',
            'isRequired',
            'isMain',
        ];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->normalizeBooleanLikeValues($value);

                continue;
            }

            if (! is_string($value) || ! in_array((string) $key, $booleanKeys, true)) {
                continue;
            }

            $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($normalized !== null) {
                $payload[$key] = $normalized;
            }
        }

        return $payload;
    }
}
