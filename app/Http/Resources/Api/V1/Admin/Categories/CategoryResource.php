<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Categories;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nameAr' => $this->name_ar,
            'nameEn' => $this->name_en,
            'descriptionAr' => $this->description_ar,
            'descriptionEn' => $this->description_en,
            'slugAr' => $this->slug_ar,
            'slugEn' => $this->slug_en,
            'image' => $this->imageUrl(),
            'sortOrder' => (int) $this->sort_order,
            'isActive' => (bool) $this->is_active,
            'subcategoriesCount' => (int) ($this->subcategories_count ?? 0),
            'createdAt' => $this->created_at?->toJSON(),
            'updatedAt' => $this->updated_at?->toJSON(),
            'deletedAt' => $this->deleted_at?->toJSON(),
        ];
    }
}
