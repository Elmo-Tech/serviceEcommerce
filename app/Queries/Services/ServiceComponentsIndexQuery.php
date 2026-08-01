<?php

declare(strict_types=1);

namespace App\Queries\Services;

use App\Models\Service;
use Illuminate\Support\Collection;

class ServiceComponentsIndexQuery
{
    public function specifications(Service $service): Collection
    {
        return $service->specifications()
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function orderFields(Service $service): Collection
    {
        return $service->orderFields()
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function pricingOptions(Service $service): Collection
    {
        return $service->pricingOptions()
            ->whereNull('deleted_at')
            ->with(['values' => fn ($query) => $query->whereNull('deleted_at')->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
