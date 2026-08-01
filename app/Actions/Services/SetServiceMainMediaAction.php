<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\Enums\HttpStatusCode;
use App\Enums\Services\ServiceMediaType;
use App\Exceptions\ApiBusinessException;
use App\Models\Service;
use App\Models\ServiceMedia;
use App\Services\Services\ServiceLookupService;
use Illuminate\Support\Facades\DB;

class SetServiceMainMediaAction
{
    public function __construct(
        private readonly ServiceLookupService $serviceLookupService,
    ) {}

    public function execute(Service $service, ServiceMedia $media): ServiceMedia
    {
        return DB::transaction(function () use ($service, $media): ServiceMedia {
            $lockedService = $this->serviceLookupService->lockOrFail($service->getKey(), true);

            $lockedMedia = $lockedService->media()
                ->whereKey($media->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedMedia instanceof ServiceMedia) {
                throw new ApiBusinessException(
                    'service_media.errors.not_found',
                    'SERVICE_MEDIA_NOT_FOUND',
                    HttpStatusCode::NOT_FOUND,
                );
            }

            if ($lockedMedia->type !== ServiceMediaType::IMAGE) {
                throw new ApiBusinessException(
                    'service_media.errors.only_images_can_be_main',
                    'ONLY_IMAGES_CAN_BE_MAIN',
                    HttpStatusCode::UNPROCESSABLE_ENTITY,
                );
            }

            $lockedService->media()
                ->where('type', ServiceMediaType::IMAGE)
                ->update(['is_main' => false]);

            $lockedMedia->forceFill(['is_main' => true])->save();

            return $lockedMedia;
        });
    }
}
