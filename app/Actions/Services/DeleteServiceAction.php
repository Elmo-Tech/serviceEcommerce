<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\Models\Service;
use App\Services\Services\ServiceLookupService;
use Illuminate\Support\Facades\DB;

class DeleteServiceAction
{
    public function __construct(
        private readonly ServiceLookupService $serviceLookupService,
    ) {}

    public function execute(Service $service): void
    {
        DB::transaction(function () use ($service): void {
            $lockedService = $this->serviceLookupService->lockOrFail($service->getKey(), true);
            $lockedService->delete();
        });
    }
}
