<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;
use App\Services\Categories\CategoryImageService;
use App\Services\Categories\CategoryLookupService;
use App\Services\Categories\LocalizedSlugService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateSubcategoryAction
{
    public function __construct(
        private readonly CategoryLookupService $categoryLookupService,
        private readonly LocalizedSlugService $localizedSlugService,
        private readonly CategoryImageService $categoryImageService,
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
        $storedImage = $this->storeImage($payload['image'] ?? null);

        try {
            return DB::transaction(function () use ($rootCategory, $payload, $nameAr, $nameEn, $slugs, $storedImage): Category {
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
                    'image_disk' => $storedImage['disk'] ?? null,
                    'image_path' => $storedImage['path'] ?? null,
                ]);
            });
        } catch (Throwable $throwable) {
            $this->deleteStoredImage($storedImage);

            throw $throwable;
        }
    }

    private function normalizeName(string $value): string
    {
        $trimmed = trim($value);

        return preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;
    }

    /**
     * @return array{disk:string,path:string}|null
     */
    private function storeImage(mixed $image): ?array
    {
        if (! $image instanceof UploadedFile) {
            return null;
        }

        return $this->categoryImageService->store($image);
    }

    private function deleteStoredImage(?array $storedImage): void
    {
        if ($storedImage === null) {
            return;
        }

        $this->categoryImageService->delete($storedImage['disk'] ?? null, $storedImage['path'] ?? null);
    }
}
