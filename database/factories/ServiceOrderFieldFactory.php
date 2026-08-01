<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Services\ServiceOrderFieldType;
use App\Models\Service;
use App\Models\ServiceOrderField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOrderField>
 */
class ServiceOrderFieldFactory extends Factory
{
    protected $model = ServiceOrderField::class;

    public function definition(): array
    {
        $suffix = fake()->unique()->numberBetween(1000, 9999);

        return [
            'service_id' => Service::factory(),
            'label_ar' => 'حقل '.$suffix,
            'label_en' => 'Field '.$suffix,
            'field_type' => ServiceOrderFieldType::TEXT,
            'is_required' => fake()->boolean(),
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
