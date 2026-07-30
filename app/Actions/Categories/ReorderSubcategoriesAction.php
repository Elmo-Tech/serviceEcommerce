<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;
use App\Services\Categories\CategoryLookupService;
use Illuminate\Support\Facades\DB;

class ReorderSubcategoriesAction
{
    public function __construct(
        private readonly CategoryLookupService $categoryLookupService,
    ) {}

    /**
     * @param  list<int>  $orderedIds
     */
    public function execute(Category $rootCategory, array $orderedIds): void
    {
        DB::transaction(function () use ($rootCategory, $orderedIds): void {
            $lockedRoot = $this->categoryLookupService->lockRootOrFail($rootCategory->getKey(), true);
            $this->categoryLookupService->ensureActiveForMutation($lockedRoot);

            $lockedSubcategories = $lockedRoot->children()
                ->whereNull('deleted_at')
                ->whereIn('id', $orderedIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lockedSubcategories->count() !== count($orderedIds)) {
                $this->categoryLookupService->throwSubcategoryNotFound();
            }

            foreach (array_values($orderedIds) as $sortOrder => $subcategoryId) {
                $lockedSubcategory = $lockedSubcategories->firstWhere('id', $subcategoryId);

                if (! $lockedSubcategory instanceof Category) {
                    $this->categoryLookupService->throwSubcategoryNotFound();
                }

                $lockedSubcategory->forceFill([
                    'sort_order' => $sortOrder,
                ])->save();
            }
        });
    }
}
