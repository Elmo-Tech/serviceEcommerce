<?php

declare(strict_types=1);

namespace App\Services\Services;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Support\Collection;

class ServiceHierarchyLockCoordinator
{
    /**
     * @return Collection<int, Category>
     */
    public function lockRootCategories(array $categoryIds): Collection
    {
        $ids = collect($categoryIds)
            ->filter(static fn ($id): bool => $id !== null)
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Category::query()
            ->withTrashed()
            ->roots()
            ->whereIn('id', $ids->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @return Collection<int, Category>
     */
    public function lockSubcategories(array $subcategoryIds): Collection
    {
        $ids = collect($subcategoryIds)
            ->filter(static fn ($id): bool => $id !== null)
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Category::query()
            ->withTrashed()
            ->subcategories()
            ->whereIn('id', $ids->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @return Collection<int, Service>
     */
    public function lockServices(array $serviceIds, bool $withTrashed = true): Collection
    {
        $ids = collect($serviceIds)
            ->filter(static fn ($id): bool => $id !== null)
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $query = Service::query();

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query
            ->whereIn('id', $ids->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
