<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $phoneSeed = (string) fake()->unique()->numerify('100#######');

        return [
            'name' => fake()->name(),
            'email' => mb_strtolower(fake()->unique()->safeEmail()),
            'phone' => '+20 '.$phoneSeed,
            'phone_normalized' => '+20'.$phoneSeed,
        ];
    }

    public function withoutEmail(): static
    {
        return $this->state(fn (): array => [
            'email' => null,
        ]);
    }

    public function deleted(): static
    {
        return $this->state(fn (): array => [
            'deleted_at' => now(),
        ]);
    }
}
