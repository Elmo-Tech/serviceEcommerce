<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Categories;

use App\Models\Category;
use App\Services\Categories\CategoryLocaleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $localeService = app(CategoryLocaleService::class);

        return [
            'id' => $this->id,
            'name' => $localeService->value($this->resource, 'name'),
            'description' => $localeService->value($this->resource, 'description'),
            'slug' => $localeService->value($this->resource, 'slug'),
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
