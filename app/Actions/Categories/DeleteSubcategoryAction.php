<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;
use App\Services\Categories\CategoryLookupService;
use Illuminate\Support\Facades\DB;

class DeleteSubcategoryAction
{
    public function __construct(
        private readonly CategoryLookupService $categoryLookupService,
    ) {}

    public function execute(Category $rootCategory, Category $subcategory): void
    {
        DB::transaction(function () use ($rootCategory, $subcategory): void {
            $lockedRoot = $this->categoryLookupService->lockRootOrFail($rootCategory->getKey(), true);
            $this->categoryLookupService->ensureActiveForMutation($lockedRoot);

            $lockedSubcategory = $this->categoryLookupService->lockScopedSubcategoryOrFail($lockedRoot, $subcategory->getKey());
            $this->categoryLookupService->ensureActiveForMutation($lockedSubcategory, true);

            $lockedSubcategory->delete();
        });
    }
}
