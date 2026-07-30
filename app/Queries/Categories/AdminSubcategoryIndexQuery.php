<?php

declare(strict_types=1);

namespace App\Queries\Categories;

use App\Models\Category;
use App\Queries\Categories\Filters\CategorySearchFilter;
use App\Queries\Categories\Sorts\LocalizedCategoryNameSort;
use App\Services\Categories\CategoryLocaleService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class AdminSubcategoryIndexQuery
{
    public function __construct(
        private readonly CategoryLocaleService $categoryLocaleService,
    ) {}

    public function paginate(Category $rootCategory, array $filters): LengthAwarePaginator
    {
        $baseQuery = $rootCategory->children();

        $trashed = $filters['trashed'] ?? 'without';

        if ($trashed === 'with') {
            $baseQuery->withTrashed();
        } elseif ($trashed === 'only') {
            $baseQuery->onlyTrashed();
        } else {
            $baseQuery->whereNull('deleted_at');
        }

        $query = QueryBuilder::for($baseQuery)
            ->allowedFilters(
                AllowedFilter::custom('search', new CategorySearchFilter),
                AllowedFilter::exact('isActive', 'is_active'),
                AllowedFilter::callback('trashed', static function (): void {}),
            )
            ->allowedSorts(
                AllowedSort::field('sortOrder', 'sort_order'),
                AllowedSort::custom('name', new LocalizedCategoryNameSort($this->categoryLocaleService)),
                AllowedSort::field('createdAt', 'created_at'),
                AllowedSort::field('updatedAt', 'updated_at'),
            );

        return $query
            ->defaultSort('sortOrder')
            ->paginate((int) ($filters['perPage'] ?? 20), ['*'], 'page', (int) ($filters['page'] ?? 1))
            ->withQueryString();
    }
}
