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

class DeleteSubcategoryAction
{
    public function __construct(
        private readonly CategoryLookupService $categoryLookupService,
        private readonly ServiceHierarchyLockCoordinator $serviceHierarchyLockCoordinator,
    ) {}

    public function execute(Category $rootCategory, Category $subcategory): void
    {
        DB::transaction(function () use ($rootCategory, $subcategory): void {
            $lockedRoot = $this->categoryLookupService->lockRootOrFail($rootCategory->getKey(), true);
            $this->categoryLookupService->ensureActiveForMutation($lockedRoot);
            $this->serviceHierarchyLockCoordinator->lockRootCategories([$lockedRoot->getKey()]);

            $lockedSubcategory = $this->categoryLookupService->lockScopedSubcategoryOrFail($lockedRoot, $subcategory->getKey());
            $this->categoryLookupService->ensureActiveForMutation($lockedSubcategory, true);
            $this->serviceHierarchyLockCoordinator->lockSubcategories([$lockedSubcategory->getKey()]);

            $blockingServices = Service::query()
                ->where('subcategory_id', $lockedSubcategory->getKey())
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            if ($blockingServices->isNotEmpty()) {
                throw new ApiBusinessException(
                    'services.errors.subcategory_has_services',
                    'SUBCATEGORY_HAS_SERVICES',
                    HttpStatusCode::CONFLICT,
                );
            }

            $lockedSubcategory->delete();
        });
    }
}
