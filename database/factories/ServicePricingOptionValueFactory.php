<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ServicePricingOption;
use App\Models\ServicePricingOptionValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicePricingOptionValue>
 */
class ServicePricingOptionValueFactory extends Factory
{
    protected $model = ServicePricingOptionValue::class;

    public function definition(): array
    {
        $suffix = fake()->unique()->numberBetween(1000, 9999);

        return [
            'service_pricing_option_id' => ServicePricingOption::factory(),
            'label_ar' => 'قيمة '.$suffix,
            'label_en' => 'Value '.$suffix,
            'price_adjustment' => fake()->randomFloat(2, 0, 1000),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
