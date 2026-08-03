<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContactMessages\ContactMessageStatus;
use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    protected $model = ContactMessage::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '+20 101 234 5678',
            'subject' => fake()->sentence(3),
            'message' => fake()->paragraph(),
            'status' => ContactMessageStatus::NEW,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (): array => [
            'status' => ContactMessageStatus::READ,
        ]);
    }
}
