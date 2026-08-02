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

class UpdateCategoryAction
{
    public function __construct(
        private readonly CategoryLookupService $categoryLookupService,
        private readonly LocalizedSlugService $localizedSlugService,
        private readonly CategoryImageService $categoryImageService,
    ) {}

    public function execute(Category $category, array $payload): Category
    {
        $storedImage = $this->storeImage($payload['image'] ?? null);
        $previousImageDisk = null;
        $previousImagePath = null;

        try {
            $updatedCategory = DB::transaction(function () use ($category, $payload, $storedImage, &$previousImageDisk, &$previousImagePath): Category {
                $lockedCategory = $this->categoryLookupService->lockRootOrFail($category->getKey());
                $this->categoryLookupService->ensureActiveForMutation($lockedCategory);

                $previousImageDisk = $lockedCategory->image_disk;
                $previousImagePath = $lockedCategory->image_path;

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

                $lockedCategory->fill($updates);
                $lockedCategory->save();

                return $lockedCategory->fresh() ?? $lockedCategory;
            });
        } catch (Throwable $throwable) {
            $this->deleteStoredImage($storedImage);

            throw $throwable;
        }

        if ($storedImage !== null && ! ($previousImageDisk === $updatedCategory->image_disk && $previousImagePath === $updatedCategory->image_path)) {
            $this->categoryImageService->delete($previousImageDisk, $previousImagePath);
        }

        return $updatedCategory;
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
