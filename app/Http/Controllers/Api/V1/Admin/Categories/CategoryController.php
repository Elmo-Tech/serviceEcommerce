<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Categories;

use App\Actions\Categories\CreateCategoryAction;
use App\Actions\Categories\DeleteCategoryAction;
use App\Actions\Categories\ReorderCategoriesAction;
use App\Actions\Categories\RestoreCategoryAction;
use App\Actions\Categories\UpdateCategoryAction;
use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Categories\ListCategoriesRequest;
use App\Http\Requests\Api\V1\Admin\Categories\ReorderCategoriesRequest;
use App\Http\Requests\Api\V1\Admin\Categories\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Admin\Categories\UpdateCategoryRequest;
use App\Http\Resources\Api\V1\Admin\Categories\CategoryIndexResource;
use App\Http\Resources\Api\V1\Admin\Categories\CategoryResource;
use App\Queries\Categories\AdminCategoryIndexQuery;
use App\Services\Categories\CategoryLookupService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        private readonly AdminCategoryIndexQuery $adminCategoryIndexQuery,
        private readonly CategoryLookupService $categoryLookupService,
        private readonly CreateCategoryAction $createCategoryAction,
        private readonly UpdateCategoryAction $updateCategoryAction,
        private readonly DeleteCategoryAction $deleteCategoryAction,
        private readonly RestoreCategoryAction $restoreCategoryAction,
        private readonly ReorderCategoriesAction $reorderCategoriesAction,
    ) {}

    public function index(ListCategoriesRequest $request): JsonResponse
    {
        $categories = $this->adminCategoryIndexQuery->paginate($request->filters());

        return ApiResponse::withAuthenticationHeaders(
            response()->json([
                'success' => true,
                'message' => __('categories.listed'),
                'data' => CategoryIndexResource::collection(collect($categories->items()))->resolve($request),
                'meta' => [
                    'currentPage' => $categories->currentPage(),
                    'perPage' => $categories->perPage(),
                    'total' => $categories->total(),
                    'lastPage' => $categories->lastPage(),
                ],
            ], HttpStatusCode::OK->value),
        );
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->createCategoryAction->execute($request->payload());

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('categories.created'),
                (new CategoryResource($this->categoryLookupService->loadRootDetailOrFail($category->getKey())))->resolve($request),
                HttpStatusCode::CREATED,
            ),
        );
    }

    public function show(Request $request, int|string $category): JsonResponse
    {
        $resolvedCategory = $this->categoryLookupService->loadRootDetailOrFail((int) $category);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('categories.retrieved'),
                (new CategoryResource($resolvedCategory))->resolve($request),
            ),
        );
    }

    public function update(UpdateCategoryRequest $request, int|string $category): JsonResponse
    {
        $resolvedCategory = $this->categoryLookupService->findRootOrFail((int) $category, true);
        $updatedCategory = $this->updateCategoryAction->execute($resolvedCategory, $request->payload());

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('categories.updated'),
                (new CategoryResource($this->categoryLookupService->loadRootDetailOrFail($updatedCategory->getKey())))->resolve($request),
            ),
        );
    }

    public function destroy(int|string $category): JsonResponse
    {
        $resolvedCategory = $this->categoryLookupService->findRootOrFail((int) $category, true);
        $this->deleteCategoryAction->execute($resolvedCategory);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('categories.deleted'),
                null,
            ),
        );
    }

    public function restore(Request $request, int|string $category): JsonResponse
    {
        $resolvedCategory = $this->categoryLookupService->findRootOrFail((int) $category, true);
        $restoredCategory = $this->restoreCategoryAction->execute($resolvedCategory);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('categories.restored'),
                (new CategoryResource($this->categoryLookupService->loadRootDetailOrFail($restoredCategory->getKey())))->resolve($request),
            ),
        );
    }

    public function reorder(ReorderCategoriesRequest $request): JsonResponse
    {
        $this->reorderCategoriesAction->execute($request->orderedIds());

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('categories.reordered'),
                null,
            ),
        );
    }
}
