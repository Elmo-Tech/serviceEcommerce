<?php

declare(strict_types=1);

namespace App\Queries\Services;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Service;

class PublicServiceLookupQuery
{
    public function findOrFail(string $serviceSlug): Service
    {
        $service = Service::query()
            ->active()
            ->whereNull('deleted_at')
            ->where(function ($query) use ($serviceSlug): void {
                $query->where('slug_ar', $serviceSlug)
                    ->orWhere('slug_en', $serviceSlug);
            })
            ->with([
                'category',
                'subcategory',
                'specifications',
                'orderFields',
                'pricingOptions.values',
                'media',
            ])
            ->first();

        if (! $service instanceof Service) {
            throw new ApiBusinessException(
                'auth.resource_not_found',
                'RESOURCE_NOT_FOUND',
                HttpStatusCode::NOT_FOUND,
            );
        }

        return $service;
    }
}
