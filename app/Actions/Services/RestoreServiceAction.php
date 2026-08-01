<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Service;
use App\Services\Services\ServiceHierarchyLockCoordinator;
use App\Services\Services\ServiceLookupService;
use Illuminate\Support\Facades\DB;

class RestoreServiceAction
{
    public function __construct(
        private readonly ServiceLookupService $serviceLookupService,
        private readonly ServiceHierarchyLockCoordinator $serviceHierarchyLockCoordinator,
    ) {}

    public function execute(Service $service): Service
    {
        return DB::transaction(function () use ($service): Service {
            $snapshot = $this->serviceLookupService->findOrFail($service->getKey(), true);
            $lockedRootCategories = $this->serviceHierarchyLockCoordinator->lockRootCategories([$snapshot->category_id]);
            $lockedSubcategories = $this->serviceHierarchyLockCoordinator->lockSubcategories([$snapshot->subcategory_id]);
            $lockedService = $this->serviceLookupService->lockOrFail($service->getKey(), true);

            if (! $lockedService->trashed()) {
                throw new ApiBusinessException(
                    'services.errors.not_deleted',
                    'SERVICE_NOT_DELETED',
                    HttpStatusCode::CONFLICT,
                );
            }

            $category = $lockedService->category_id
                ? $lockedRootCategories->firstWhere('id', $lockedService->category_id)
                : null;
            $subcategory = $lockedService->subcategory_id
                ? $lockedSubcategories->firstWhere('id', $lockedService->subcategory_id)
                : null;

            if ($category?->trashed()) {
                $lockedService->category_id = null;
                $lockedService->subcategory_id = null;
            } elseif ($subcategory?->trashed()) {
                $lockedService->subcategory_id = null;
            }

            $lockedService->restore();
            $lockedService->is_active = false;
            $lockedService->save();

            return $lockedService;
        });
    }
}
