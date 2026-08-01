<?php

declare(strict_types=1);

namespace App\Services\Services;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Service;
use Illuminate\Database\QueryException;

class ServiceSlugReservationService
{
    public function sync(Service $service, string $slugAr, string $slugEn): void
    {
        $normalized = collect([$slugAr, $slugEn])
            ->filter(static fn (string $slug): bool => $slug !== '')
            ->unique()
            ->sort()
            ->values();

        $service->slugReservations()
            ->orderBy('slug')
            ->lockForUpdate()
            ->get();

        $service->slugReservations()
            ->whereNotIn('slug', $normalized->all())
            ->delete();

        try {
            foreach ($normalized as $slug) {
                $service->slugReservations()->firstOrCreate([
                    'slug' => $slug,
                ]);
            }
        } catch (QueryException $exception) {
            throw new ApiBusinessException(
                'validation.custom.slug.unique',
                'VALIDATION_ERROR',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
                [
                    'slug' => [__('validation.unique', ['attribute' => 'slug'])],
                ],
            );
        }
    }
}
