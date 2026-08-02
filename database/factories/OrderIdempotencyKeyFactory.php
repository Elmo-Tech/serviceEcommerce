<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderIdempotencyKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrderIdempotencyKey>
 */
class OrderIdempotencyKeyFactory extends Factory
{
    protected $model = OrderIdempotencyKey::class;

    public function definition(): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(),
            'request_fingerprint' => hash('sha256', (string) Str::uuid()),
            'order_id' => Order::factory(),
            'reserved_at' => now(),
            'completed_at' => now(),
        ];
    }
}
