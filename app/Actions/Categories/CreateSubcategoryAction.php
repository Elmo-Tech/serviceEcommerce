<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;
use App\Services\Categories\CategoryLookupService;
use App\Services\Categories\LocalizedSlugService;
use Illuminate\Support\Facades\DB;

class CreateSubcategoryAction
{
    public function __construct(
        private readonly CategoryLookupService $categoryLookupService,
        private readonly LocalizedSlugService $localizedSlugService,
    ) {}

    public function execute(Category $rootCategory, array $payload): Category
    {
        $nameAr = $this->normalizeName((string) $payload['nameAr']);
        $nameEn = $this->normalizeName((string) $payload['nameEn']);
        $slugs = $this->localizedSlugService->generatePairFromNames(
            $nameAr,
            $nameEn,
            $payload['slugAr'] ?? null,
            $payload['slugEn'] ?? null,
        );

        return DB::transaction(function () use ($rootCategory, $payload, $nameAr, $nameEn, $slugs): Category {
            $lockedRoot = $this->categoryLookupService->lockRootOrFail($rootCategory->getKey());
            $this->categoryLookupService->ensureActiveForMutation($lockedRoot);

            return Category::query()->create([
                'parent_id' => $lockedRoot->getKey(),
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'description_ar' => $payload['descriptionAr'] ?? null,
                'description_en' => $payload['descriptionEn'] ?? null,
                'slug_ar' => $slugs['slugAr'],
                'slug_en' => $slugs['slugEn'],
                'sort_order' => (int) ($payload['sortOrder'] ?? 0),
                'is_active' => (bool) ($payload['isActive'] ?? true),
            ]);
        });
    }

    private function normalizeName(string $value): string
    {
        $trimmed = trim($value);

        return preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;
    }
}
