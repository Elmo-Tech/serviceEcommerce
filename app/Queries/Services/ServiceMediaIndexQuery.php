<?php

declare(strict_types=1);

namespace App\Queries\Services;

use App\Models\Service;
use Illuminate\Support\Collection;

class ServiceMediaIndexQuery
{
    public function get(Service $service): Collection
    {
        return $service->media()
            ->orderByDesc('is_main')
            ->orderBy('type')
            ->orderBy('id')
            ->get();
    }
}
