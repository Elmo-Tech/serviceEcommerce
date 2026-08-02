<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Services\ServicePriceType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $suffix = fake()->unique()->numberBetween(1000, 9999);

        return [
            'order_id' => Order::factory(),
            'service_id' => Service::factory(),
            'service_name_ar' => 'خدمة '.$suffix,
            'service_name_en' => 'Service '.$suffix,
            'service_slug_ar' => 'خدمة-'.$suffix,
            'service_slug_en' => 'service-'.$suffix,
            'price_type' => ServicePriceType::FIXED,
            'base_price' => '500.00',
            'unit_price' => '500.00',
            'quantity' => 1,
            'item_total' => '500.00',
            'item_note' => null,
        ];
    }
}
