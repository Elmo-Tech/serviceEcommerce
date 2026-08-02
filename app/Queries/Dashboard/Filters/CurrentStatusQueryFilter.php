<?php

declare(strict_types=1);

namespace App\Queries\Dashboard\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as BaseBuilder;

class CurrentStatusQueryFilter
{
    public function __invoke(Builder|BaseBuilder $query, ?int $status): Builder|BaseBuilder
    {
        if ($status === null) {
            return $query;
        }

        return $query->where('status', $status);
    }
}
