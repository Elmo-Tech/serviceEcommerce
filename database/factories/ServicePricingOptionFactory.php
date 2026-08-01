<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Services\ServicePricingInputType;
use App\Enums\Services\ServicePricingOptionType;
use App\Models\Service;
use App\Models\ServicePricingOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicePricingOption>
 */
class ServicePricingOptionFactory extends Factory
{
    protected $model = ServicePricingOption::class;

    public function definition(): array
    {
        $suffix = fake()->unique()->numberBetween(1000, 9999);

        return [
            'service_id' => Service::factory()->startFrom(),
            'name_ar' => 'خيار '.$suffix,
            'name_en' => 'Option '.$suffix,
            'option_type' => ServicePricingOptionType::ADD_ON,
            'input_type' => ServicePricingInputType::SELECT,
            'is_required' => fake()->boolean(),
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
