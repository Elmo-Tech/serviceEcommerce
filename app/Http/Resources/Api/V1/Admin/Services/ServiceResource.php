<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Services;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Service
 */
class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'categoryId' => $this->category_id,
            'subcategoryId' => $this->subcategory_id,
            'nameAr' => $this->name_ar,
            'nameEn' => $this->name_en,
            'shortDescriptionAr' => $this->short_description_ar,
            'shortDescriptionEn' => $this->short_description_en,
            'descriptionAr' => $this->description_ar,
            'descriptionEn' => $this->description_en,
            'slugAr' => $this->slug_ar,
            'slugEn' => $this->slug_en,
            'productionTimeAr' => $this->production_time_ar,
            'productionTimeEn' => $this->production_time_en,
            'priceType' => $this->price_type?->value,
            'basePrice' => (float) $this->base_price,
            'isActive' => (bool) $this->is_active,
            'isAvailable' => (bool) $this->is_available,
            'seoTitleAr' => $this->seo_title_ar,
            'seoTitleEn' => $this->seo_title_en,
            'seoDescriptionAr' => $this->seo_description_ar,
            'seoDescriptionEn' => $this->seo_description_en,
            'seoTagsAr' => $this->seo_tags_ar,
            'seoTagsEn' => $this->seo_tags_en,
            'specifications' => $this->specifications->map(fn ($item) => [
                'id' => $item->id,
                'labelAr' => $item->label_ar,
                'labelEn' => $item->label_en,
                'valueAr' => $item->value_ar,
                'valueEn' => $item->value_en,
                'sortOrder' => (int) $item->sort_order,
            ])->values()->all(),
            'orderFields' => $this->orderFields->map(fn ($item) => [
                'id' => $item->id,
                'labelAr' => $item->label_ar,
                'labelEn' => $item->label_en,
                'fieldType' => $item->field_type?->value,
                'isRequired' => (bool) $item->is_required,
                'sortOrder' => (int) $item->sort_order,
            ])->values()->all(),
            'pricingOptions' => $this->pricingOptions->map(fn ($item) => [
                'id' => $item->id,
                'nameAr' => $item->name_ar,
                'nameEn' => $item->name_en,
                'inputType' => $item->input_type?->value,
                'isRequired' => (bool) $item->is_required,
                'sortOrder' => (int) $item->sort_order,
                'values' => $item->values->map(fn ($value) => [
                    'id' => $value->id,
                    'labelAr' => $value->label_ar,
                    'labelEn' => $value->label_en,
                    'priceAdjustment' => (float) $value->price_adjustment,
                    'isActive' => (bool) $value->is_active,
                    'sortOrder' => (int) $value->sort_order,
                ])->values()->all(),
            ])->values()->all(),
            'media' => $this->media->map(fn ($item) => [
                'id' => $item->id,
                'type' => $item->type?->value,
                'url' => Storage::disk($item->disk)->url($item->path),
                'originalName' => $item->original_name,
                'mimeType' => $item->mime_type,
                'extension' => $item->extension,
                'sizeBytes' => (int) $item->size_bytes,
                'altTextAr' => $item->alt_text_ar,
                'altTextEn' => $item->alt_text_en,
                'isMain' => (bool) $item->is_main,
            ])->values()->all(),
            'createdAt' => $this->created_at?->toJSON(),
            'updatedAt' => $this->updated_at?->toJSON(),
            'deletedAt' => $this->deleted_at?->toJSON(),
        ];
    }
}
