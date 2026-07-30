<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Categories;

use App\Actions\Categories\CreateSubcategoryAction;
use App\Actions\Categories\DeleteSubcategoryAction;
use App\Actions\Categories\ReorderSubcategoriesAction;
use App\Actions\Categories\RestoreSubcategoryAction;
use App\Actions\Categories\UpdateSubcategoryAction;
use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Categories\ListSubcategoriesRequest;
use App\Http\Requests\Api\V1\Admin\Categories\ReorderSubcategoriesRequest;
use App\Http\Requests\Api\V1\Admin\Categories\StoreSubcategoryRequest;
use App\Http\Requests\Api\V1\Admin\Categories\UpdateSubcategoryRequest;
use App\Http\Resources\Api\V1\Admin\Categories\SubcategoryIndexResource;
use App\Http\Resources\Api\V1\Admin\Categories\SubcategoryResource;
use App\Queries\Categories\AdminSubcategoryIndexQuery;
use App\Services\Categories\CategoryLookupService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubcategoryController extends Controller
{
    public function __construct(
        private readonly AdminSubcategoryIndexQuery $adminSubcategoryIndexQuery,
        private readonly CategoryLookupService $categoryLookupService,
        private readonly CreateSubcategoryAction $createSubcategoryAction,
        private readonly UpdateSubcategoryAction $updateSubcategoryAction,
        private readonly DeleteSubcategoryAction $deleteSubcategoryAction,
        private readonly RestoreSubcategoryAction $restoreSubcategoryAction,
        private readonly ReorderSubcategoriesAction $reorderSubcategoriesAction,
    ) {}

    public function index(ListSubcategoriesRequest $request, int|string $category): JsonResponse
    {
        $rootCategory = $this->categoryLookupService->findRootOrFail((int) $category, true);
        $subcategories = $this->adminSubcategoryIndexQuery->paginate($rootCategory, $request->filters());

        return ApiResponse::withAuthenticationHeaders(
            response()->json([
                'success' => true,
                'message' => __('categories.subcategories_listed'),
                'data' => SubcategoryIndexResource::collection(collect($subcategories->items()))->resolve($request),
                'meta' => [
                    'currentPage' => $subcategories->currentPage(),
                    'perPage' => $subcategories->perPage(),
                    'total' => $subcategories->total(),
                    'lastPage' => $subcategories->lastPage(),
                ],
            ], HttpStatusCode::OK->value),
        );
    }

    public function store(StoreSubcategoryRequest $request, int|string $category): JsonResponse
    {
        $rootCategory = $this->categoryLookupService->findRootOrFail((int) $category, true);
        $subcategory = $this->createSubcategoryAction->execute($rootCategory, $request->payload());

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('categories.subcategory_created'),
                (new SubcategoryResource(
                    $this->categoryLookupService->loadScopedSubcategoryDetailOrFail($rootCategory, $subcategory->getKey())
                ))->resolve($request),
                HttpStatusCode::CREATED,
            ),
        );
    }

    public function show(Request $request, int|string $category, int|string $subcategory): JsonResponse
    {
        $rootCategory = $this->categoryLookupService->findRootOrFail((int) $category, true);
        $resolvedSubcategory = $this->categoryLookupService->loadScopedSubcategoryDetailOrFail($rootCategory, (int) $subcategory);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('categories.subcategory_retrieved'),
                (new SubcategoryResource($resolvedSubcategory))->resolve($request),
            ),
        );
    }

    public function update(UpdateSubcategoryRequest $request, int|string $category, int|string $subcategory): JsonResponse
    {
        $rootCategory = $this->categoryLookupService->findRootOrFail((int) $category, true);
        $resolvedSubcategory = $this->categoryLookupService->findScopedSubcategoryOrFail($rootCategory, (int) $subcategory, true);
        $updatedSubcategory = $this->updateSubcategoryAction->execute($rootCategory, $resolvedSubcategory, $request->payload());

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('categories.subcategory_updated'),
                (new SubcategoryResource(
                    $this->categoryLookupService->loadScopedSubcategoryDetailOrFail($rootCategory, $updatedSubcategory->getKey())
                ))->resolve($request),
            ),
        );
    }

    public function destroy(int|string $category, int|string $subcategory): JsonResponse
    {
        $rootCategory = $this->categoryLookupService->findRootOrFail((int) $category, true);
        $resolvedSubcategory = $this->categoryLookupService->findScopedSubcategoryOrFail($rootCategory, (int) $subcategory, true);
        $this->deleteSubcategoryAction->execute($rootCategory, $resolvedSubcategory);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('categories.subcategory_deleted'),
                null,
            ),
        );
    }

    public function restore(Request $request, int|string $category, int|string $subcategory): JsonResponse
    {
        $rootCategory = $this->categoryLookupService->findRootOrFail((int) $category, true);
        $resolvedSubcategory = $this->categoryLookupService->findScopedSubcategoryOrFail($rootCategory, (int) $subcategory, true);
        $restoredSubcategory = $this->restoreSubcategoryAction->execute($rootCategory, $resolvedSubcategory);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('categories.subcategory_restored'),
                (new SubcategoryResource(
                    $this->categoryLookupService->loadScopedSubcategoryDetailOrFail($rootCategory, $restoredSubcategory->getKey())
                ))->resolve($request),
            ),
        );
    }

    public function reorder(ReorderSubcategoriesRequest $request, int|string $category): JsonResponse
    {
        $rootCategory = $this->categoryLookupService->findRootOrFail((int) $category, true);
        $this->reorderSubcategoriesAction->execute($rootCategory, $request->orderedIds());

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('categories.subcategories_reordered'),
                null,
            ),
        );
    }
}
