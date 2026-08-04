<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Public\Services;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Service */
class PublicServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $category = $this->category;
        $subcategory = $this->subcategory;

        return [
            'id' => $this->id,
            'name' => $locale === 'en' ? $this->name_en : $this->name_ar,
            'shortDescription' => $locale === 'en' ? $this->short_description_en : $this->short_description_ar,
            'description' => $locale === 'en' ? $this->description_en : $this->description_ar,
            'slug' => $locale === 'en' ? $this->slug_en : $this->slug_ar,
            'priceType' => $this->price_type?->value,
            'basePrice' => (float) $this->base_price,
            'isAvailable' => (bool) $this->is_available,
            'isAttachmentRequired' => (bool) $this->is_attachment_required,
            'productionTime' => $locale === 'en' ? $this->production_time_en : $this->production_time_ar,
            'seo' => [
                'title' => ($locale === 'en' ? $this->seo_title_en : $this->seo_title_ar) ?: ($locale === 'en' ? $this->name_en : $this->name_ar),
                'description' => ($locale === 'en' ? $this->seo_description_en : $this->seo_description_ar) ?: ($locale === 'en' ? $this->short_description_en : $this->short_description_ar),
                'tags' => ($locale === 'en' ? $this->seo_tags_en : $this->seo_tags_ar) ?? [],
            ],
            'category' => $category && $category->is_active ? [
                'name' => $locale === 'en' ? $category->name_en : $category->name_ar,
                'slug' => $locale === 'en' ? $category->slug_en : $category->slug_ar,
            ] : null,
            'subcategory' => $subcategory && $subcategory->is_active ? [
                'name' => $locale === 'en' ? $subcategory->name_en : $subcategory->name_ar,
                'slug' => $locale === 'en' ? $subcategory->slug_en : $subcategory->slug_ar,
            ] : null,
            'specifications' => $this->specifications->whereNull('deleted_at')->sortBy(['sort_order', 'id'])->values()->map(fn ($item) => [
                'id' => $item->id,
                'label' => $locale === 'en' ? $item->label_en : $item->label_ar,
                'value' => $locale === 'en' ? $item->value_en : $item->value_ar,
            ])->all(),
            'orderFields' => $this->orderFields->whereNull('deleted_at')->sortBy(['sort_order', 'id'])->values()->map(fn ($item) => [
                'id' => $item->id,
                'label' => $locale === 'en' ? $item->label_en : $item->label_ar,
                'isRequired' => (bool) $item->is_required,
            ])->all(),
            'pricingOptions' => $this->pricingOptions->whereNull('deleted_at')->sortBy(['sort_order', 'id'])->values()->map(fn ($option) => [
                'id' => $option->id,
                'name' => $locale === 'en' ? $option->name_en : $option->name_ar,
                'inputType' => $option->input_type?->value,
                'isRequired' => (bool) $option->is_required,
                'values' => $option->values->whereNull('deleted_at')->where('is_active', true)->sortBy(['sort_order', 'id'])->values()->map(fn ($value) => [
                    'id' => $value->id,
                    'label' => $locale === 'en' ? $value->label_en : $value->label_ar,
                    'priceAdjustment' => (float) $value->price_adjustment,
                ])->all(),
            ])->all(),
            'media' => $this->media->sortByDesc('is_main')->values()->map(fn ($item) => [
                'id' => $item->id,
                'type' => $item->type?->value,
                'url' => Storage::disk($item->disk)->url($item->path),
                'alt' => $locale === 'en' ? $item->alt_text_en : $item->alt_text_ar,
                'isMain' => (bool) $item->is_main,
            ])->all(),
        ];
    }
}
