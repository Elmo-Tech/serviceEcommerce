<?php

declare(strict_types=1);

namespace App\Services\HeroSlides;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use Closure;
use Illuminate\Database\QueryException;
use Throwable;

class HeroSlideMutationRetrier
{
    private const MAX_ATTEMPTS = 3;

    /**
     * @template T
     *
     * @param  Closure(): T  $attempt
     * @return T
     */
    public function execute(Closure $attempt): mixed
    {
        for ($number = 1; $number <= self::MAX_ATTEMPTS; $number++) {
            try {
                return $attempt();
            } catch (Throwable $throwable) {
                if (! $this->isRetryable($throwable)) {
                    throw $throwable;
                }

                if ($number === self::MAX_ATTEMPTS) {
                    throw new ApiBusinessException(
                        'hero_slides.mutation_failed',
                        'HERO_SLIDE_MUTATION_FAILED',
                        HttpStatusCode::INTERNAL_SERVER_ERROR,
                    );
                }
            }
        }

        throw new ApiBusinessException(
            'hero_slides.mutation_failed',
            'HERO_SLIDE_MUTATION_FAILED',
            HttpStatusCode::INTERNAL_SERVER_ERROR,
        );
    }

    private function isRetryable(Throwable $throwable): bool
    {
        if (! $throwable instanceof QueryException) {
            return false;
        }

        $sqlState = (string) ($throwable->errorInfo[0] ?? $throwable->getCode());
        $driverCode = (int) ($throwable->errorInfo[1] ?? 0);

        return $sqlState === '40001' || $driverCode === 1213;
    }
}
