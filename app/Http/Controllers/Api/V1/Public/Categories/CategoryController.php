<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Categories;

use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Public\Categories\PublicCategoryResource;
use App\Http\Resources\Api\V1\Public\Categories\PublicSubcategoryResource;
use App\Queries\Categories\PublicCategoryCatalogQuery;
use App\Services\Categories\CategoryLocaleService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        private readonly PublicCategoryCatalogQuery $publicCategoryCatalogQuery,
        private readonly CategoryLocaleService $categoryLocaleService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $categories = $this->publicCategoryCatalogQuery->listVisibleRootCategories();

        return ApiResponse::withAuthenticationHeaders(
            response()->json([
                'success' => true,
                'message' => __('categories.listed'),
                'data' => PublicCategoryResource::collection($categories)->resolve($request),
                'meta' => $this->localeMeta(),
            ], HttpStatusCode::OK->value),
        );
    }

    public function show(Request $request, string $categorySlug): JsonResponse
    {
        $category = $this->publicCategoryCatalogQuery->findVisibleRootCategoryOrFail($categorySlug);

        return ApiResponse::withAuthenticationHeaders(
            response()->json([
                'success' => true,
                'message' => __('categories.retrieved'),
                'data' => (new PublicCategoryResource($category))->resolve($request),
                'meta' => [
                    ...$this->localeMeta(),
                    'localeLinks' => $this->publicCategoryCatalogQuery->categoryLocaleLinks($category),
                ],
            ], HttpStatusCode::OK->value),
        );
    }

    public function indexSubcategories(Request $request, string $categorySlug): JsonResponse
    {
        $category = $this->publicCategoryCatalogQuery->findVisibleRootCategoryOrFail($categorySlug);
        $subcategories = $this->publicCategoryCatalogQuery->listVisibleSubcategories($category);

        return ApiResponse::withAuthenticationHeaders(
            response()->json([
                'success' => true,
                'message' => __('categories.subcategories_listed'),
                'data' => PublicSubcategoryResource::collection($subcategories)->resolve($request),
                'meta' => [
                    ...$this->localeMeta(),
                    'localeLinks' => $this->publicCategoryCatalogQuery->categoryLocaleLinks($category),
                ],
            ], HttpStatusCode::OK->value),
        );
    }

    public function showSubcategory(Request $request, string $categorySlug, string $subcategorySlug): JsonResponse
    {
        $category = $this->publicCategoryCatalogQuery->findVisibleRootCategoryOrFail($categorySlug);
        $subcategory = $this->publicCategoryCatalogQuery->findVisibleSubcategoryOrFail($category, $subcategorySlug);

        return ApiResponse::withAuthenticationHeaders(
            response()->json([
                'success' => true,
                'message' => __('categories.subcategory_retrieved'),
                'data' => (new PublicSubcategoryResource($subcategory))->resolve($request),
                'meta' => [
                    ...$this->localeMeta(),
                    'localeLinks' => $this->publicCategoryCatalogQuery->subcategoryLocaleLinks($category, $subcategory),
                ],
            ], HttpStatusCode::OK->value),
        );
    }

    private function localeMeta(): array
    {
        $locale = $this->categoryLocaleService->resolvedLocale();

        return [
            'locale' => $locale,
            'direction' => $this->categoryLocaleService->direction($locale),
        ];
    }
}
