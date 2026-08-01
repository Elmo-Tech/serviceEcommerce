<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\Enums\HttpStatusCode;
use App\Enums\Services\ServiceMediaType;
use App\Exceptions\ApiBusinessException;
use App\Models\Service;
use App\Models\ServiceMedia;
use App\Services\Files\ServiceMediaStorageService;
use App\Services\Services\ServiceChildLimitGuard;
use App\Services\Services\ServiceLookupService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UploadServiceMediaAction
{
    public function __construct(
        private readonly ServiceLookupService $serviceLookupService,
        private readonly ServiceChildLimitGuard $serviceChildLimitGuard,
        private readonly ServiceMediaStorageService $serviceMediaStorageService,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $mediaPayloads
     * @return Collection<int, ServiceMedia>
     */
    public function execute(Service $service, array $mediaPayloads): Collection
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($service, $mediaPayloads, &$storedFiles): Collection {
                $lockedService = $this->serviceLookupService->lockOrFail($service->getKey(), true);
                $created = collect();

                foreach ($mediaPayloads as $payload) {
                    $type = (int) $payload['type'];

                    if ($type === ServiceMediaType::IMAGE->value) {
                        $this->serviceChildLimitGuard->assertImageLimit($lockedService);

                        $wantsMain = (bool) ($payload['isMain'] ?? false);
                        $hasMainImage = $lockedService->media()->where('type', ServiceMediaType::IMAGE)->where('is_main', true)->exists();
                        $isMain = ! $hasMainImage || $wantsMain;

                        if ($wantsMain) {
                            $this->serviceChildLimitGuard->ensureSingleMainImage($lockedService);
                        }
                    } else {
                        $this->serviceChildLimitGuard->assertVideoLimit($lockedService);
                        $isMain = false;

                        if ($lockedService->media()->where('type', ServiceMediaType::VIDEO)->lockForUpdate()->exists()) {
                            throw new ApiBusinessException(
                                'service_media.errors.video_limit_reached',
                                'SERVICE_VIDEO_LIMIT_REACHED',
                                HttpStatusCode::UNPROCESSABLE_ENTITY,
                            );
                        }
                    }

                    $stored = $this->serviceMediaStorageService->storeUploadedFile($payload['file']);
                    $storedFiles[] = $stored;

                    $created->push($lockedService->media()->create([
                        ...$stored,
                        'type' => $type,
                        'alt_text_ar' => $payload['altAr'] ?? null,
                        'alt_text_en' => $payload['altEn'] ?? null,
                        'is_main' => $isMain,
                    ]));

                    if ($type === ServiceMediaType::IMAGE->value && $isMain) {
                        $lockedService->media()
                            ->where('type', ServiceMediaType::IMAGE)
                            ->whereKeyNot($created->last()->getKey())
                            ->update(['is_main' => false]);
                    }
                }

                return $created;
            });
        } catch (\Throwable $throwable) {
            foreach ($storedFiles as $storedFile) {
                $this->serviceMediaStorageService->deleteStoredFile($storedFile);
            }

            throw $throwable;
        }
    }
}
