<?php

declare(strict_types=1);

namespace App\Queries\Categories\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class CategorySearchFilter implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $search = trim((string) $value);

        if ($search === '') {
            return;
        }

        $query->where(function (Builder $nestedQuery) use ($search): void {
            $like = '%'.$search.'%';

            $nestedQuery
                ->where('name_ar', 'like', $like)
                ->orWhere('name_en', 'like', $like)
                ->orWhere('description_ar', 'like', $like)
                ->orWhere('description_en', 'like', $like)
                ->orWhere('slug_ar', 'like', $like)
                ->orWhere('slug_en', 'like', $like);
        });
    }
}
