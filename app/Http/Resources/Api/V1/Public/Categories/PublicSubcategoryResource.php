<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Public\Categories;

use App\Models\Category;
use App\Services\Categories\CategoryLocaleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class PublicSubcategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $localeService = app(CategoryLocaleService::class);

        return [
            'name' => $localeService->value($this->resource, 'name'),
            'description' => $localeService->value($this->resource, 'description'),
            'slug' => $localeService->value($this->resource, 'slug'),
        ];
    }
}
