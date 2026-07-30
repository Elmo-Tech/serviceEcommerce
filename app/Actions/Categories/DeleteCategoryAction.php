<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Category;
use App\Services\Categories\CategoryLookupService;
use Illuminate\Support\Facades\DB;

class DeleteCategoryAction
{
    public function __construct(
        private readonly CategoryLookupService $categoryLookupService,
    ) {}

    public function execute(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            $lockedCategory = $this->categoryLookupService->lockRootOrFail($category->getKey());
            $this->categoryLookupService->ensureActiveForMutation($lockedCategory);

            $activeChildren = $lockedCategory->children()
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            if ($activeChildren->isNotEmpty()) {
                throw new ApiBusinessException(
                    'categories.errors.category_has_subcategories',
                    'CATEGORY_HAS_SUBCATEGORIES',
                    HttpStatusCode::CONFLICT,
                );
            }

            $lockedCategory->delete();
        });
    }
}
