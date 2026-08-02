<?php

declare(strict_types=1);

namespace App\Queries\Dashboard\Filters;

use App\Enums\Orders\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as BaseBuilder;

class NonCancelledQueryFilter
{
    public function __invoke(Builder|BaseBuilder $query): Builder|BaseBuilder
    {
        return $query->where('status', '!=', OrderStatus::CANCELLED->value);
    }
}
