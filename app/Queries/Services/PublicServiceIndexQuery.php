<?php

declare(strict_types=1);

namespace App\Queries\Services;

use App\Models\Service;
use App\Queries\Services\Filters\LocalizedServiceSearchFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PublicServiceIndexQuery
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $baseQuery = Service::query()
            ->active()
            ->whereNull('deleted_at')
            ->with(['category', 'subcategory', 'mainImage']);

        $query = QueryBuilder::for($baseQuery)
            ->allowedFilters(
                AllowedFilter::custom('search', new LocalizedServiceSearchFilter),
                AllowedFilter::callback('category', function ($query, $value): void {
                    $query->where(function ($builder) use ($value): void {
                        $builder->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('slug_ar', $value)->orWhere('slug_en', $value));
                    });
                }),
                AllowedFilter::callback('subcategory', function ($query, $value): void {
                    $query->where(function ($builder) use ($value): void {
                        $builder->whereHas('subcategory', fn ($categoryQuery) => $categoryQuery->where('slug_ar', $value)->orWhere('slug_en', $value));
                    });
                }),
                AllowedFilter::callback('priceFrom', function ($query, $value): void {
                    if ($value !== null && $value !== '') {
                        $query->where('base_price', '>=', (float) $value);
                    }
                }),
                AllowedFilter::callback('priceTo', function ($query, $value): void {
                    if ($value !== null && $value !== '') {
                        $query->where('base_price', '<=', (float) $value);
                    }
                }),
                AllowedFilter::exact('isAvailable', 'is_available'),
            );

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['perPage'] ?? 12), ['*'], 'page', (int) ($filters['page'] ?? 1))
            ->withQueryString();
    }
}
