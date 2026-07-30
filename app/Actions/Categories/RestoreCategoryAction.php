<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Category;
use App\Services\Categories\CategoryLookupService;
use Illuminate\Support\Facades\DB;

class RestoreCategoryAction
{
    public function __construct(
        private readonly CategoryLookupService $categoryLookupService,
    ) {}

    public function execute(Category $category): Category
    {
        return DB::transaction(function () use ($category): Category {
            $lockedCategory = $this->categoryLookupService->lockRootOrFail($category->getKey());

            if (! $lockedCategory->trashed()) {
                throw new ApiBusinessException(
                    'categories.errors.category_not_deleted',
                    'CATEGORY_NOT_DELETED',
                    HttpStatusCode::CONFLICT,
                );
            }

            $lockedCategory->restore();

            return $lockedCategory->fresh() ?? $lockedCategory;
        });
    }
}
