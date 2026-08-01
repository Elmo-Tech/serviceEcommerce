<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    public function definition(): array
    {
        $phoneSeed = (string) fake()->numerify('100#######');
        $province = fake()->city();
        $city = fake()->city();
        $address = fake()->streetAddress();

        return [
            'customer_id' => Customer::factory(),
            'phone' => '+20 '.$phoneSeed,
            'phone_normalized' => '+20'.$phoneSeed,
            'province' => $province,
            'city' => $city,
            'address' => $address,
            'notes' => fake()->optional()->sentence(),
            'address_hash' => hash('sha256', mb_strtolower(implode('|', [$province, $city, $address]))),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => [
            'is_default' => true,
        ]);
    }

    public function deleted(): static
    {
        return $this->state(fn (): array => [
            'deleted_at' => now(),
        ]);
    }
}
