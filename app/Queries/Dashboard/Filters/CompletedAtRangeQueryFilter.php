<?php

declare(strict_types=1);

namespace App\Queries\Dashboard\Filters;

use App\Data\Dashboard\DashboardDateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as BaseBuilder;

class CompletedAtRangeQueryFilter
{
    public function __invoke(Builder|BaseBuilder $query, DashboardDateRange $range): Builder|BaseBuilder
    {
        return $query
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $range->startAt)
            ->where('completed_at', '<', $range->exclusiveEndAt);
    }
}
