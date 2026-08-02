<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OrderNumberSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderNumberSequence>
 */
class OrderNumberSequenceFactory extends Factory
{
    protected $model = OrderNumberSequence::class;

    public function definition(): array
    {
        return [
            'business_date' => now()->toDateString(),
            'last_sequence' => fake()->numberBetween(0, 9999),
        ];
    }
}
