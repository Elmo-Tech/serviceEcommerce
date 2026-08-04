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
class ServiceIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $mainImage = $this->mainImage;

        return [
            'id' => $this->id,
            'name' => $locale === 'en' ? $this->name_en : $this->name_ar,
            'shortDescription' => $locale === 'en' ? $this->short_description_en : $this->short_description_ar,
            'slug' => $locale === 'en' ? $this->slug_en : $this->slug_ar,
            'priceType' => $this->price_type?->value,
            'basePrice' => (float) $this->base_price,
            'isActive' => (bool) $this->is_active,
            'isAvailable' => (bool) $this->is_available,
            'isAttachmentRequired' => (bool) $this->is_attachment_required,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $locale === 'en' ? $this->category->name_en : $this->category->name_ar,
                'slug' => $locale === 'en' ? $this->category->slug_en : $this->category->slug_ar,
            ] : null,
            'subcategory' => $this->subcategory ? [
                'id' => $this->subcategory->id,
                'name' => $locale === 'en' ? $this->subcategory->name_en : $this->subcategory->name_ar,
                'slug' => $locale === 'en' ? $this->subcategory->slug_en : $this->subcategory->slug_ar,
            ] : null,
            'mainImageUrl' => $mainImage ? Storage::disk($mainImage->disk)->url($mainImage->path) : null,
            'createdAt' => $this->created_at?->toJSON(),
            'updatedAt' => $this->updated_at?->toJSON(),
            'deletedAt' => $this->deleted_at?->toJSON(),
        ];
    }
}
