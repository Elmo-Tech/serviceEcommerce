<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceSlugReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceSlugReservation>
 */
class ServiceSlugReservationFactory extends Factory
{
    protected $model = ServiceSlugReservation::class;

    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'slug' => 'service-slug-'.fake()->unique()->numberBetween(1000, 9999),
        ];
    }
}
