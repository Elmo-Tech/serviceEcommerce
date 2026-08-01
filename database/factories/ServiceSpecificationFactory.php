<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceSpecification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceSpecification>
 */
class ServiceSpecificationFactory extends Factory
{
    protected $model = ServiceSpecification::class;

    public function definition(): array
    {
        $suffix = fake()->unique()->numberBetween(1000, 9999);

        return [
            'service_id' => Service::factory(),
            'label_ar' => 'مواصفة '.$suffix,
            'label_en' => 'Specification '.$suffix,
            'value_ar' => 'قيمة '.$suffix,
            'value_en' => 'Value '.$suffix,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
