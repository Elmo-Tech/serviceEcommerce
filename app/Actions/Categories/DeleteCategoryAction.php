<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Category;
use App\Models\Service;
use App\Services\Categories\CategoryLookupService;
use App\Services\Services\ServiceHierarchyLockCoordinator;
use Illuminate\Support\Facades\DB;

class DeleteCategoryAction
{
    public function __construct(
        private readonly CategoryLookupService $categoryLookupService,
        private readonly ServiceHierarchyLockCoordinator $serviceHierarchyLockCoordinator,
    ) {}

    public function execute(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            $lockedCategory = $this->categoryLookupService->lockRootOrFail($category->getKey());
            $this->categoryLookupService->ensureActiveForMutation($lockedCategory);
            $this->serviceHierarchyLockCoordinator->lockRootCategories([$lockedCategory->getKey()]);

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

            $blockingServices = Service::query()
                ->where('category_id', $lockedCategory->getKey())
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            if ($blockingServices->isNotEmpty()) {
                throw new ApiBusinessException(
                    'services.errors.category_has_services',
                    'CATEGORY_HAS_SERVICES',
                    HttpStatusCode::CONFLICT,
                );
            }

            $lockedCategory->delete();
        });
    }
}
