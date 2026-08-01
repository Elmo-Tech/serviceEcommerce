<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Public\Services;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Service */
class PublicServiceListItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $mainImage = $this->mainImage;
        $category = $this->category;
        $subcategory = $this->subcategory;

        return [
            'id' => $this->id,
            'name' => $locale === 'en' ? $this->name_en : $this->name_ar,
            'shortDescription' => $locale === 'en' ? $this->short_description_en : $this->short_description_ar,
            'slug' => $locale === 'en' ? $this->slug_en : $this->slug_ar,
            'priceType' => $this->price_type?->value,
            'basePrice' => (float) $this->base_price,
            'isAvailable' => (bool) $this->is_available,
            'mainMedia' => $mainImage ? [
                'id' => $mainImage->id,
                'type' => $mainImage->type?->value,
                'url' => Storage::disk($mainImage->disk)->url($mainImage->path),
                'alt' => $locale === 'en' ? $mainImage->alt_text_en : $mainImage->alt_text_ar,
                'isMain' => (bool) $mainImage->is_main,
            ] : null,
            'category' => $category && $category->is_active ? [
                'name' => $locale === 'en' ? $category->name_en : $category->name_ar,
                'slug' => $locale === 'en' ? $category->slug_en : $category->slug_ar,
            ] : null,
            'subcategory' => $subcategory && $subcategory->is_active ? [
                'name' => $locale === 'en' ? $subcategory->name_en : $subcategory->name_ar,
                'slug' => $locale === 'en' ? $subcategory->slug_en : $subcategory->slug_ar,
            ] : null,
        ];
    }
}
