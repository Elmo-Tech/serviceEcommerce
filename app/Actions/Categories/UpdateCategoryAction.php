<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;
use App\Services\Categories\CategoryLookupService;
use App\Services\Categories\LocalizedSlugService;
use Illuminate\Support\Facades\DB;

class UpdateCategoryAction
{
    public function __construct(
        private readonly CategoryLookupService $categoryLookupService,
        private readonly LocalizedSlugService $localizedSlugService,
    ) {}

    public function execute(Category $category, array $payload): Category
    {
        return DB::transaction(function () use ($category, $payload): Category {
            $lockedCategory = $this->categoryLookupService->lockRootOrFail($category->getKey());
            $this->categoryLookupService->ensureActiveForMutation($lockedCategory);

            $updates = [];

            if (array_key_exists('nameAr', $payload)) {
                $updates['name_ar'] = $this->normalizeName((string) $payload['nameAr']);
            }

            if (array_key_exists('nameEn', $payload)) {
                $updates['name_en'] = $this->normalizeName((string) $payload['nameEn']);
            }

            if (array_key_exists('descriptionAr', $payload) || array_key_exists('descriptionEn', $payload)) {
                $updates['description_ar'] = $payload['descriptionAr'] ?? null;
                $updates['description_en'] = $payload['descriptionEn'] ?? null;
            }

            if (array_key_exists('slugAr', $payload)) {
                $updates['slug_ar'] = $this->localizedSlugService->normalizeArabic((string) $payload['slugAr']);
            }

            if (array_key_exists('slugEn', $payload)) {
                $updates['slug_en'] = $this->localizedSlugService->normalizeEnglish((string) $payload['slugEn']);
            }

            if (array_key_exists('sortOrder', $payload)) {
                $updates['sort_order'] = (int) $payload['sortOrder'];
            }

            if (array_key_exists('isActive', $payload)) {
                $updates['is_active'] = (bool) $payload['isActive'];
            }

            $lockedCategory->fill($updates);
            $lockedCategory->save();

            return $lockedCategory->fresh() ?? $lockedCategory;
        });
    }

    private function normalizeName(string $value): string
    {
        $trimmed = trim($value);

        return preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;
    }
}
