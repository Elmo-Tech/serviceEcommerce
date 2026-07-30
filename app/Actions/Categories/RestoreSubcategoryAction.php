<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Category;
use App\Services\Categories\CategoryLookupService;
use Illuminate\Support\Facades\DB;

class RestoreSubcategoryAction
{
    public function __construct(
        private readonly CategoryLookupService $categoryLookupService,
    ) {}

    public function execute(Category $rootCategory, Category $subcategory): Category
    {
        return DB::transaction(function () use ($rootCategory, $subcategory): Category {
            $lockedRoot = $this->categoryLookupService->lockRootOrFail($rootCategory->getKey(), true);
            $lockedSubcategory = $this->categoryLookupService->lockScopedSubcategoryOrFail($lockedRoot, $subcategory->getKey(), true);

            if (! $lockedSubcategory->trashed()) {
                throw new ApiBusinessException(
                    'categories.errors.subcategory_not_deleted',
                    'SUBCATEGORY_NOT_DELETED',
                    HttpStatusCode::CONFLICT,
                );
            }

            if ($lockedRoot->trashed()) {
                throw new ApiBusinessException(
                    'categories.errors.parent_category_deleted',
                    'PARENT_CATEGORY_DELETED',
                    HttpStatusCode::CONFLICT,
                );
            }

            $lockedSubcategory->restore();

            return $lockedSubcategory->fresh() ?? $lockedSubcategory;
        });
    }
}
