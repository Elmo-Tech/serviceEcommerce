<?php

declare(strict_types=1);

namespace App\Services\Services;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Category;
use Illuminate\Support\Collection;

class ServiceClassificationValidator
{
    /**
     * @return array{0: Category|null, 1: Category|null}
     */
    public function validate(?int $categoryId, ?int $subcategoryId): array
    {
        if ($categoryId === null && $subcategoryId !== null) {
            throw new ApiBusinessException(
                'services.errors.subcategory_requires_category',
                'SUBCATEGORY_REQUIRES_CATEGORY',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }

        $category = null;
        $subcategory = null;

        if ($categoryId !== null) {
            $category = Category::query()
                ->roots()
                ->whereKey($categoryId)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->first();

            if (! $category instanceof Category) {
                throw new ApiBusinessException(
                    'services.errors.category_not_available',
                    'CATEGORY_NOT_AVAILABLE',
                    HttpStatusCode::UNPROCESSABLE_ENTITY,
                );
            }
        }

        if ($subcategoryId !== null) {
            $subcategory = Category::query()
                ->subcategories()
                ->whereKey($subcategoryId)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->first();

            if (! $subcategory instanceof Category) {
                throw new ApiBusinessException(
                    'services.errors.subcategory_not_available',
                    'SUBCATEGORY_NOT_AVAILABLE',
                    HttpStatusCode::UNPROCESSABLE_ENTITY,
                );
            }

            if ((int) $subcategory->parent_id !== $category?->getKey()) {
                throw new ApiBusinessException(
                    'services.errors.subcategory_parent_mismatch',
                    'SUBCATEGORY_PARENT_MISMATCH',
                    HttpStatusCode::UNPROCESSABLE_ENTITY,
                );
            }
        }

        return [$category, $subcategory];
    }

    /**
     * @param  Collection<int, Category>  $lockedRootCategories
     * @param  Collection<int, Category>  $lockedSubcategories
     * @return array{0: Category|null, 1: Category|null}
     */
    public function validateLocked(
        ?int $categoryId,
        ?int $subcategoryId,
        Collection $lockedRootCategories,
        Collection $lockedSubcategories,
    ): array {
        if ($categoryId === null && $subcategoryId !== null) {
            throw new ApiBusinessException(
                'services.errors.subcategory_requires_category',
                'SUBCATEGORY_REQUIRES_CATEGORY',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }

        /** @var Category|null $category */
        $category = $categoryId === null
            ? null
            : $lockedRootCategories->firstWhere('id', $categoryId);

        /** @var Category|null $subcategory */
        $subcategory = $subcategoryId === null
            ? null
            : $lockedSubcategories->firstWhere('id', $subcategoryId);

        if ($categoryId !== null && (! $category instanceof Category || $category->trashed() || ! $category->is_active)) {
            throw new ApiBusinessException(
                'services.errors.category_not_available',
                'CATEGORY_NOT_AVAILABLE',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }

        if ($subcategoryId !== null && (! $subcategory instanceof Category || $subcategory->trashed() || ! $subcategory->is_active)) {
            throw new ApiBusinessException(
                'services.errors.subcategory_not_available',
                'SUBCATEGORY_NOT_AVAILABLE',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }

        if ($subcategory instanceof Category && (int) $subcategory->parent_id !== $category?->getKey()) {
            throw new ApiBusinessException(
                'services.errors.subcategory_parent_mismatch',
                'SUBCATEGORY_PARENT_MISMATCH',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }

        return [$category, $subcategory];
    }
}
