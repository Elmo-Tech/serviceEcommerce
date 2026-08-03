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

class CreateHeroSlideAction
{
    public function __construct(
        private readonly HeroSlideImageService $imageService,
        private readonly HeroSlideOrderingService $orderingService,
        private readonly HeroSlideMutationRetrier $mutationRetrier,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(array $payload): HeroSlide
    {
        /** @var UploadedFile $image */
        $image = $payload['image'];
        $stored = $this->imageService->store($image);

        try {
            return $this->mutationRetrier->execute(fn (): HeroSlide => $this->orderingService->mutate(function ($slides) use ($payload, $stored): HeroSlide {
                $count = $slides->count();

                if ($count >= 10) {
                    throw new ApiBusinessException(
                        'hero_slides.limit_reached',
                        'VALIDATION_ERROR',
                        HttpStatusCode::UNPROCESSABLE_ENTITY,
                        ['payload' => [__('hero_slides.limit_reached')]],
                    );
                }

                $position = (int) ($payload['position'] ?? ($count + 1));

                if ($position < 1 || $position > $count + 1) {
                    throw new ApiBusinessException(
                        'validation.invalid_payload',
                        'VALIDATION_ERROR',
                        HttpStatusCode::UNPROCESSABLE_ENTITY,
                        ['position' => [__('validation.invalid_payload')]],
                    );
                }

                $orderedIds = $slides->modelKeys();
                $this->orderingService->park($slides);

                $slide = HeroSlide::query()->create([
                    'title_ar' => $payload['titleAr'],
                    'title_en' => $payload['titleEn'],
                    'description_ar' => $payload['descriptionAr'],
                    'description_en' => $payload['descriptionEn'],
                    'image_path' => $stored['path'],
                    'is_active' => (bool) $payload['isActive'],
                    'position' => HeroSlideOrderingService::TEMPORARY_NEW_POSITION,
                ]);

                array_splice($orderedIds, $position - 1, 0, [$slide->getKey()]);
                $this->orderingService->assign(array_map('intval', $orderedIds));

                return $slide->refresh();
            }));
        } catch (Throwable $throwable) {
            try {
                $this->imageService->delete($stored['path']);
            } catch (Throwable) {
                Log::warning('hero_slides.rollback_image_cleanup_failed');
            }

            throw $throwable;
        }
    }
}
