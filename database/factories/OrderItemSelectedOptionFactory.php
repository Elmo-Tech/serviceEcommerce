<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Services\ServicePricingInputType;
use App\Models\OrderItem;
use App\Models\OrderItemSelectedOption;
use App\Models\ServicePricingOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItemSelectedOption>
 */
class OrderItemSelectedOptionFactory extends Factory
{
    protected $model = OrderItemSelectedOption::class;

    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'pricing_option_id' => ServicePricingOption::factory(),
            'option_name_ar' => 'المقاس',
            'option_name_en' => 'Size',
            'input_type' => ServicePricingInputType::SELECT,
            'is_required' => true,
        ];
    }
}
