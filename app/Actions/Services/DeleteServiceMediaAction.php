<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\Enums\Services\ServiceMediaType;
use App\Models\Service;
use App\Models\ServiceMedia;
use App\Services\Files\ServiceMediaStorageService;
use App\Services\Services\ServiceLookupService;
use Illuminate\Support\Facades\DB;

class DeleteServiceMediaAction
{
    public function __construct(
        private readonly ServiceLookupService $serviceLookupService,
        private readonly ServiceMediaStorageService $serviceMediaStorageService,
    ) {}

    public function execute(Service $service, ServiceMedia $media): void
    {
        DB::transaction(function () use ($service, $media): void {
            $lockedService = $this->serviceLookupService->lockOrFail($service->getKey(), true);
            $lockedMedia = $lockedService->media()->whereKey($media->getKey())->lockForUpdate()->firstOrFail();

            $wasMainImage = $lockedMedia->type === ServiceMediaType::IMAGE && $lockedMedia->is_main;

            $this->serviceMediaStorageService->deleteStoredFile($lockedMedia);
            $lockedMedia->delete();

            if ($wasMainImage) {
                $fallback = $lockedService->media()
                    ->where('type', ServiceMediaType::IMAGE)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($fallback instanceof ServiceMedia) {
                    $fallback->forceFill(['is_main' => true])->save();
                }
            }
        });
    }
}
