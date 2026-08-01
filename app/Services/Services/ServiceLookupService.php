<?php

declare(strict_types=1);

namespace App\Services\Services;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Service;

class ServiceLookupService
{
    public function findOrFail(int $serviceId, bool $withTrashed = false): Service
    {
        $query = Service::query();

        if ($withTrashed) {
            $query->withTrashed();
        }

        $service = $query->find($serviceId);

        if (! $service instanceof Service) {
            $this->throwNotFound();
        }

        return $service;
    }

    public function lockOrFail(int $serviceId, bool $withTrashed = false): Service
    {
        $query = Service::query();

        if ($withTrashed) {
            $query->withTrashed();
        }

        $service = $query->whereKey($serviceId)->lockForUpdate()->first();

        if (! $service instanceof Service) {
            $this->throwNotFound();
        }

        return $service;
    }

    public function loadDetailOrFail(int $serviceId, bool $withTrashed = true): Service
    {
        $query = Service::query()
            ->withTrashed()
            ->with([
                'category',
                'subcategory',
                'specifications' => fn ($query) => $query->whereNull('deleted_at')->orderBy('sort_order')->orderBy('id'),
                'orderFields' => fn ($query) => $query->whereNull('deleted_at')->orderBy('sort_order')->orderBy('id'),
                'pricingOptions' => fn ($query) => $query->whereNull('deleted_at')->orderBy('sort_order')->orderBy('id'),
                'pricingOptions.values' => fn ($query) => $query->whereNull('deleted_at')->orderBy('sort_order')->orderBy('id'),
                'media' => fn ($query) => $query->orderByDesc('is_main')->orderBy('id'),
            ]);

        if (! $withTrashed) {
            $query->whereNull('deleted_at');
        }

        $service = $query->find($serviceId);

        if (! $service instanceof Service) {
            $this->throwNotFound();
        }

        return $service;
    }

    public function throwNotFound(): never
    {
        throw new ApiBusinessException(
            'services.errors.not_found',
            'SERVICE_NOT_FOUND',
            HttpStatusCode::NOT_FOUND,
        );
    }
}
