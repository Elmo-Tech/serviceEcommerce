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
        $city = fake()->city();
        $area = fake()->optional()->streetSuffix();
        $street = fake()->streetAddress();

        return [
            'customer_id' => Customer::factory(),
            'label' => fake()->optional()->randomElement(['Home', 'Office', 'Branch']),
            'phone' => '+20 '.$phoneSeed,
            'phone_normalized' => '+20'.$phoneSeed,
            'country_code' => 'EG',
            'city' => $city,
            'area' => $area,
            'street' => $street,
            'notes' => fake()->optional()->sentence(),
            'address_hash' => hash('sha256', mb_strtolower(implode('|', ['EG', $city, $area ?? '', $street]))),
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
