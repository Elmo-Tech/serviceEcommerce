<?php

declare(strict_types=1);

namespace App\Services\Categories;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;

class CategoryLookupService
{
    public function findRootOrFail(int $categoryId, bool $withTrashed = false): Category
    {
        $query = Category::query()->roots();

        if ($withTrashed) {
            $query->withTrashed();
        }

        $category = $query->find($categoryId);

        if (! $category instanceof Category) {
            $this->throwCategoryNotFound();
        }

        return $category;
    }

    public function findScopedSubcategoryOrFail(Category $rootCategory, int $subcategoryId, bool $withTrashed = false): Category
    {
        $query = $rootCategory->children();

        if ($withTrashed) {
            $query->withTrashed();
        }

        $subcategory = $query->find($subcategoryId);

        if (! $subcategory instanceof Category) {
            $this->throwSubcategoryNotFound();
        }

        return $subcategory;
    }

    public function loadRootDetailOrFail(int $categoryId): Category
    {
        $category = Category::query()
            ->roots()
            ->withTrashed()
            ->withCount([
                'children as subcategories_count' => fn (Builder $query) => $query->whereNull('deleted_at'),
            ])
            ->find($categoryId);

        if (! $category instanceof Category) {
            $this->throwCategoryNotFound();
        }

        return $category;
    }

    public function loadScopedSubcategoryDetailOrFail(Category $rootCategory, int $subcategoryId): Category
    {
        $subcategory = $rootCategory->children()
            ->withTrashed()
            ->with('parent')
            ->find($subcategoryId);

        if (! $subcategory instanceof Category) {
            $this->throwSubcategoryNotFound();
        }

        return $subcategory;
    }

    public function lockRootOrFail(int $categoryId, bool $withTrashed = true): Category
    {
        $query = Category::query()->roots();

        if ($withTrashed) {
            $query->withTrashed();
        }

        $category = $query
            ->whereKey($categoryId)
            ->lockForUpdate()
            ->first();

        if (! $category instanceof Category) {
            $this->throwCategoryNotFound();
        }

        return $category;
    }

    public function lockScopedSubcategoryOrFail(Category $rootCategory, int $subcategoryId, bool $withTrashed = true): Category
    {
        $query = $rootCategory->children();

        if ($withTrashed) {
            $query->withTrashed();
        }

        $subcategory = $query
            ->whereKey($subcategoryId)
            ->lockForUpdate()
            ->first();

        if (! $subcategory instanceof Category) {
            $this->throwSubcategoryNotFound();
        }

        return $subcategory;
    }

    public function ensureActiveForMutation(Category $category, bool $subcategory = false): void
    {
        if ($category->trashed()) {
            if ($subcategory) {
                $this->throwSubcategoryNotFound();
            }

            $this->throwCategoryNotFound();
        }
    }

    public function throwCategoryNotFound(): never
    {
        throw new ApiBusinessException(
            'categories.errors.category_not_found',
            'CATEGORY_NOT_FOUND',
            HttpStatusCode::NOT_FOUND,
        );
    }

    public function throwSubcategoryNotFound(): never
    {
        throw new ApiBusinessException(
            'categories.errors.subcategory_not_found',
            'SUBCATEGORY_NOT_FOUND',
            HttpStatusCode::NOT_FOUND,
        );
    }
}
