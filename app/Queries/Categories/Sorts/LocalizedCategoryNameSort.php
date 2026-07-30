<?php

declare(strict_types=1);

namespace App\Queries\Categories\Sorts;

use App\Services\Categories\CategoryLocaleService;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class LocalizedCategoryNameSort implements Sort
{
    public function __construct(
        private readonly CategoryLocaleService $categoryLocaleService,
    ) {}

    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $direction = $descending ? 'desc' : 'asc';
        $column = $this->categoryLocaleService->field('name');

        $query->orderBy($column, $direction)
            ->orderBy('id', $direction);
    }
}
