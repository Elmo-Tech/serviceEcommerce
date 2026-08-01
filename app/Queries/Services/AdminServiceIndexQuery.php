<?php

declare(strict_types=1);

namespace App\Queries\Services;

use App\Models\Service;
use App\Queries\Services\Filters\LocalizedServiceSearchFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class AdminServiceIndexQuery
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $baseQuery = Service::query()
            ->with(['category', 'subcategory', 'mainImage']);

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
                AllowedFilter::custom('search', new LocalizedServiceSearchFilter),
                AllowedFilter::exact('categoryId', 'category_id'),
                AllowedFilter::exact('subcategoryId', 'subcategory_id'),
                AllowedFilter::exact('priceType', 'price_type'),
                AllowedFilter::exact('isActive', 'is_active'),
                AllowedFilter::exact('isAvailable', 'is_available'),
                AllowedFilter::callback('trashed', static function (): void {}),
            )
            ->allowedSorts(
                AllowedSort::field('basePrice', 'base_price'),
                AllowedSort::field('createdAt', 'created_at'),
            );

        return $query
            ->defaultSort('-createdAt')
            ->paginate((int) ($filters['perPage'] ?? 20), ['*'], 'page', (int) ($filters['page'] ?? 1))
            ->withQueryString();
    }
}
