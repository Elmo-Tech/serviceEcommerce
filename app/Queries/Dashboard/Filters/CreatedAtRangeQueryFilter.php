<?php

declare(strict_types=1);

namespace App\Queries\Dashboard\Filters;

use App\Data\Dashboard\DashboardDateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as BaseBuilder;

class CreatedAtRangeQueryFilter
{
    public function __invoke(Builder|BaseBuilder $query, DashboardDateRange $range): Builder|BaseBuilder
    {
        return $query
            ->where('created_at', '>=', $range->startAt)
            ->where('created_at', '<', $range->exclusiveEndAt);
    }
}
