<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;
use App\Services\Categories\CategoryLookupService;
use Illuminate\Support\Facades\DB;

class ReorderCategoriesAction
{
    public function __construct(
        private readonly CategoryLookupService $categoryLookupService,
    ) {}

    /**
     * @param  list<int>  $orderedIds
     */
    public function execute(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds): void {
            $lockedCategories = Category::query()
                ->roots()
                ->whereNull('deleted_at')
                ->whereIn('id', $orderedIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lockedCategories->count() !== count($orderedIds)) {
                $this->categoryLookupService->throwCategoryNotFound();
            }

            foreach (array_values($orderedIds) as $sortOrder => $categoryId) {
                $lockedCategory = $lockedCategories->firstWhere('id', $categoryId);

                if (! $lockedCategory instanceof Category) {
                    $this->categoryLookupService->throwCategoryNotFound();
                }

                $lockedCategory->forceFill([
                    'sort_order' => $sortOrder,
                ])->save();
            }
        });
    }
}
