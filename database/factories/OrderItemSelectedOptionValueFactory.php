<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OrderItemSelectedOption;
use App\Models\OrderItemSelectedOptionValue;
use App\Models\ServicePricingOptionValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItemSelectedOptionValue>
 */
class OrderItemSelectedOptionValueFactory extends Factory
{
    protected $model = OrderItemSelectedOptionValue::class;

    public function definition(): array
    {
        return [
            'order_item_selected_option_id' => OrderItemSelectedOption::factory(),
            'pricing_option_value_id' => ServicePricingOptionValue::factory(),
            'value_label_ar' => 'كبير',
            'value_label_en' => 'Large',
            'price_adjustment' => '100.00',
        ];
    }
}
