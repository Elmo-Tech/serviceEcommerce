<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Orders\OrderPlace;
use App\Enums\Orders\OrderStatus;
use App\Enums\Orders\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $phoneTail = (string) fake()->unique()->numerify('########');

        return [
            'order_number' => 'ORD-20260801-'.(string) fake()->unique()->numberBetween(1000, 9999),
            'customer_id' => Customer::factory(),
            'customer_name' => fake()->name(),
            'customer_phone' => '010'.$phoneTail,
            'customer_email' => fake()->safeEmail(),
            'status' => OrderStatus::PENDING,
            'order_place' => OrderPlace::WEBSITE,
            'subtotal' => '500.00',
            'discount_type' => null,
            'discount_value' => '0.00',
            'discount_amount' => '0.00',
            'total' => '500.00',
            'payment_status' => PaymentStatus::UNPAID,
            'paid_amount' => '0.00',
        ];
    }

    public function withOrderNumber(?string $orderNumber = null): static
    {
        return $this->state(fn (): array => [
            'order_number' => $orderNumber ?? 'ORD-20260801-'.(string) fake()->unique()->numberBetween(1000, 9999),
        ]);
    }
}
