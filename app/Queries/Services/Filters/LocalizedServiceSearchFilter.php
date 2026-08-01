<?php

declare(strict_types=1);

namespace App\Queries\Services\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class LocalizedServiceSearchFilter implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $search = trim($value);

        $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('name_ar', 'like', "%{$search}%")
                ->orWhere('name_en', 'like', "%{$search}%")
                ->orWhere('short_description_ar', 'like', "%{$search}%")
                ->orWhere('short_description_en', 'like', "%{$search}%");
        });
    }
}
