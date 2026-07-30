<?php

declare(strict_types=1);

namespace App\Queries\Categories;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Category;
use App\Services\Categories\CategoryLocaleService;
use Illuminate\Database\Eloquent\Collection;

class PublicCategoryCatalogQuery
{
    public function __construct(
        private readonly CategoryLocaleService $categoryLocaleService,
    ) {}

    /**
     * @return Collection<int, Category>
     */
    public function listVisibleRootCategories(): Collection
    {
        return Category::query()
            ->roots()
            ->active()
            ->ordered()
            ->with([
                'children' => fn ($query) => $query->active()->ordered(),
            ])
            ->get();
    }

    public function findVisibleRootCategoryOrFail(string $slug, ?string $locale = null): Category
    {
        $column = $this->categoryLocaleService->field('slug', $locale);

        $category = Category::query()
            ->roots()
            ->active()
            ->where($column, $slug)
            ->with([
                'children' => fn ($query) => $query->active()->ordered(),
            ])
            ->first();

        if (! $category instanceof Category) {
            $this->throwPublicNotFound();
        }

        return $category;
    }

    /**
     * @return Collection<int, Category>
     */
    public function listVisibleSubcategories(Category $rootCategory): Collection
    {
        return $rootCategory->children()
            ->active()
            ->ordered()
            ->get();
    }

    public function findVisibleSubcategoryOrFail(Category $rootCategory, string $slug, ?string $locale = null): Category
    {
        $column = $this->categoryLocaleService->field('slug', $locale);

        $subcategory = $rootCategory->children()
            ->active()
            ->where($column, $slug)
            ->first();

        if (! $subcategory instanceof Category) {
            $this->throwPublicNotFound();
        }

        return $subcategory;
    }

    public function categoryLocaleLinks(Category $rootCategory): array
    {
        return [
            'ar' => sprintf('/api/v1/public/categories/%s', $rootCategory->slug_ar),
            'en' => sprintf('/api/v1/public/categories/%s', $rootCategory->slug_en),
        ];
    }

    public function subcategoryLocaleLinks(Category $rootCategory, Category $subcategory): array
    {
        return [
            'ar' => sprintf('/api/v1/public/categories/%s/subcategories/%s', $rootCategory->slug_ar, $subcategory->slug_ar),
            'en' => sprintf('/api/v1/public/categories/%s/subcategories/%s', $rootCategory->slug_en, $subcategory->slug_en),
        ];
    }

    private function throwPublicNotFound(): never
    {
        throw new ApiBusinessException(
            'auth.resource_not_found',
            'RESOURCE_NOT_FOUND',
            HttpStatusCode::NOT_FOUND,
        );
    }
}
