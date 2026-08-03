<?php

declare(strict_types=1);

namespace App\Services\HeroSlides;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\HeroSlide;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class HeroSlideOrderingService
{
    public const TEMPORARY_NEW_POSITION = 120;

    private const LOCK_NAME = 'service-commerce:hero-slides:ordering';

    private const LOCK_TIMEOUT_SECONDS = 5;

    /**
     * @template T
     *
     * @param  Closure(Collection<int, HeroSlide>): T  $callback
     * @return T
     */
    public function mutate(Closure $callback): mixed
    {
        $connection = DB::connection();
        $lock = $connection->selectOne('SELECT GET_LOCK(?, ?) AS acquired', [
            self::LOCK_NAME,
            self::LOCK_TIMEOUT_SECONDS,
        ]);

        if ((int) ($lock->acquired ?? 0) !== 1) {
            throw new ApiBusinessException(
                'hero_slides.ordering_unavailable',
                'HERO_SLIDE_ORDERING_UNAVAILABLE',
                HttpStatusCode::SERVICE_UNAVAILABLE,
            );
        }

        try {
            return $connection->transaction(function () use ($callback): mixed {
                /** @var Collection<int, HeroSlide> $slides */
                $slides = HeroSlide::query()
                    ->ordered()
                    ->lockForUpdate()
                    ->get();

                $this->assertContiguous($slides);

                return $callback($slides);
            }, 1);
        } finally {
            try {
                $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [self::LOCK_NAME]);
            } catch (Throwable) {
                // The connection itself may already be unavailable; never mask the mutation outcome.
            }
        }
    }

    /**
     * @param  Collection<int, HeroSlide>  $slides
     */
    public function park(Collection $slides): void
    {
        foreach ($slides->values() as $index => $slide) {
            $slide->forceFill(['position' => 101 + $index])->save();
        }
    }

    /**
     * @param  list<int>  $orderedIds
     */
    public function assign(array $orderedIds): void
    {
        if (count($orderedIds) > 10 || count($orderedIds) !== count(array_unique($orderedIds))) {
            throw new RuntimeException('Invalid Hero slide order.');
        }

        foreach (array_values($orderedIds) as $index => $id) {
            $updated = HeroSlide::query()->whereKey($id)->update(['position' => $index + 1]);

            if ($updated !== 1) {
                throw new RuntimeException('Hero slide order target is missing.');
            }
        }
    }

    /**
     * @param  Collection<int, HeroSlide>  $slides
     */
    public function findOrFail(Collection $slides, int $id): HeroSlide
    {
        $slide = $slides->firstWhere('id', $id);

        if (! $slide instanceof HeroSlide) {
            throw new ApiBusinessException(
                'hero_slides.not_found',
                'HERO_SLIDE_NOT_FOUND',
                HttpStatusCode::NOT_FOUND,
            );
        }

        return $slide;
    }

    /**
     * @param  Collection<int, HeroSlide>  $slides
     */
    private function assertContiguous(Collection $slides): void
    {
        $actual = $slides->pluck('position')->map(static fn (mixed $position): int => (int) $position)->all();
        $expected = $slides->isEmpty() ? [] : range(1, $slides->count());

        if ($actual !== $expected || $slides->count() > 10) {
            throw new RuntimeException('Hero slide ordering invariant is invalid.');
        }
    }
}
