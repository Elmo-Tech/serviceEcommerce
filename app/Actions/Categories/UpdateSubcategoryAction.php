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

class UpdateSubcategoryAction
{
    public function __construct(
        private readonly CategoryLookupService $categoryLookupService,
        private readonly LocalizedSlugService $localizedSlugService,
        private readonly CategoryImageService $categoryImageService,
    ) {}

    public function execute(Category $rootCategory, Category $subcategory, array $payload): Category
    {
        $storedImage = $this->storeImage($payload['image'] ?? null);
        $previousImageDisk = null;
        $previousImagePath = null;

        try {
            $updatedSubcategory = DB::transaction(function () use ($rootCategory, $subcategory, $payload, $storedImage, &$previousImageDisk, &$previousImagePath): Category {
                $lockedRoot = $this->categoryLookupService->lockRootOrFail($rootCategory->getKey(), true);
                $this->categoryLookupService->ensureActiveForMutation($lockedRoot);

                $lockedSubcategory = $this->categoryLookupService->lockScopedSubcategoryOrFail($lockedRoot, $subcategory->getKey());
                $this->categoryLookupService->ensureActiveForMutation($lockedSubcategory, true);

                $previousImageDisk = $lockedSubcategory->image_disk;
                $previousImagePath = $lockedSubcategory->image_path;

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

                if ($storedImage !== null) {
                    $updates['image_disk'] = $storedImage['disk'];
                    $updates['image_path'] = $storedImage['path'];
                }

                $lockedSubcategory->fill($updates);
                $lockedSubcategory->save();

                return $lockedSubcategory->fresh() ?? $lockedSubcategory;
            });
        } catch (Throwable $throwable) {
            $this->deleteStoredImage($storedImage);

            throw $throwable;
        }

        if ($storedImage !== null && ! ($previousImageDisk === $updatedSubcategory->image_disk && $previousImagePath === $updatedSubcategory->image_path)) {
            $this->categoryImageService->delete($previousImageDisk, $previousImagePath);
        }

        return $updatedSubcategory;
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
