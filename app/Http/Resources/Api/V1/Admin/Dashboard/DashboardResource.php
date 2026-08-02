<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'salesPeriod' => data_get($this->resource, 'salesPeriod'),
            'sales' => data_get($this->resource, 'sales'),
            'collectedSales' => data_get($this->resource, 'collectedSales'),
            'uncollectedSales' => data_get($this->resource, 'uncollectedSales'),
            'orders' => data_get($this->resource, 'orders'),
            'performance' => data_get($this->resource, 'performance'),
        ];
    }
}
