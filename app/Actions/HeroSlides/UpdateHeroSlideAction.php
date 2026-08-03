<?php

declare(strict_types=1);

namespace App\Actions\HeroSlides;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\HeroSlide;
use App\Services\HeroSlides\HeroSlideImageService;
use App\Services\HeroSlides\HeroSlideMutationRetrier;
use App\Services\HeroSlides\HeroSlideOrderingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateHeroSlideAction
{
    public function __construct(
        private readonly HeroSlideImageService $imageService,
        private readonly HeroSlideOrderingService $orderingService,
        private readonly HeroSlideMutationRetrier $mutationRetrier,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(int $slideId, array $payload): HeroSlide
    {
        $stored = null;

        if (($payload['image'] ?? null) instanceof UploadedFile) {
            $stored = $this->imageService->store($payload['image']);
        }

        $previousPath = null;

        try {
            $updated = $this->mutationRetrier->execute(function () use ($slideId, $payload, $stored, &$previousPath): HeroSlide {
                return $this->orderingService->mutate(function ($slides) use ($slideId, $payload, $stored, &$previousPath): HeroSlide {
                    $slide = $this->orderingService->findOrFail($slides, $slideId);
                    $previousPath = $slide->image_path;
                    $updates = [];

                    foreach ([
                        'titleAr' => 'title_ar',
                        'titleEn' => 'title_en',
                        'descriptionAr' => 'description_ar',
                        'descriptionEn' => 'description_en',
                    ] as $input => $column) {
                        if (array_key_exists($input, $payload)) {
                            $updates[$column] = $payload[$input];
                        }
                    }

                    if (array_key_exists('isActive', $payload)) {
                        $updates['is_active'] = (bool) $payload['isActive'];
                    }

                    if ($stored !== null) {
                        $updates['image_path'] = $stored['path'];
                    }

                    $requestedPosition = array_key_exists('position', $payload)
                        ? (int) $payload['position']
                        : (int) $slide->position;

                    if ($requestedPosition < 1 || $requestedPosition > $slides->count()) {
                        throw new ApiBusinessException(
                            'validation.invalid_payload',
                            'VALIDATION_ERROR',
                            HttpStatusCode::UNPROCESSABLE_ENTITY,
                            ['position' => [__('validation.invalid_payload')]],
                        );
                    }

                    $slide->fill($updates)->save();

                    if ($requestedPosition !== (int) $slide->position) {
                        $orderedIds = array_values(array_filter(
                            array_map('intval', $slides->modelKeys()),
                            static fn (int $id): bool => $id !== $slideId,
                        ));
                        array_splice($orderedIds, $requestedPosition - 1, 0, [$slideId]);
                        $this->orderingService->park($slides);
                        $this->orderingService->assign($orderedIds);
                    }

                    return $slide->refresh();
                });
            });
        } catch (Throwable $throwable) {
            if ($stored !== null) {
                try {
                    $this->imageService->delete($stored['path']);
                } catch (Throwable) {
                    Log::warning('hero_slides.rollback_image_cleanup_failed', ['heroSlideId' => $slideId]);
                }
            }

            throw $throwable;
        }

        if ($stored !== null && is_string($previousPath) && $previousPath !== $stored['path']) {
            try {
                $this->imageService->delete($previousPath);
            } catch (Throwable) {
                Log::warning('hero_slides.replaced_image_cleanup_failed', ['heroSlideId' => $slideId]);
            }
        }

        return $updated;
    }
}
