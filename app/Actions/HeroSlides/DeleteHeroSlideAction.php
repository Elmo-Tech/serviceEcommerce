<?php

declare(strict_types=1);

namespace App\Actions\HeroSlides;

use App\Services\HeroSlides\HeroSlideImageService;
use App\Services\HeroSlides\HeroSlideMutationRetrier;
use App\Services\HeroSlides\HeroSlideOrderingService;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteHeroSlideAction
{
    public function __construct(
        private readonly HeroSlideImageService $imageService,
        private readonly HeroSlideOrderingService $orderingService,
        private readonly HeroSlideMutationRetrier $mutationRetrier,
    ) {}

    public function execute(int $slideId): void
    {
        $imagePath = $this->mutationRetrier->execute(fn (): string => $this->orderingService->mutate(function ($slides) use ($slideId): string {
            $slide = $this->orderingService->findOrFail($slides, $slideId);
            $path = $slide->image_path;
            $slide->delete();

            $remaining = $slides
                ->reject(static fn ($candidate): bool => (int) $candidate->getKey() === $slideId)
                ->values();

            $this->orderingService->park($remaining);
            $this->orderingService->assign(array_map('intval', $remaining->modelKeys()));

            return $path;
        }));

        try {
            $this->imageService->delete($imagePath);
        } catch (Throwable) {
            Log::warning('hero_slides.deleted_image_cleanup_failed', ['heroSlideId' => $slideId]);
        }
    }
}
