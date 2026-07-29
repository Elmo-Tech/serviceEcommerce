<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PasswordReset>
 */
class PasswordResetFactory extends Factory
{
    protected $model = PasswordReset::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resettable_type' => User::class,
            'resettable_id' => 1,
            'email_normalized' => 'admin@example.test',
            'code_hash' => bcrypt('123456'),
            'code_expires_at' => now()->addMinutes(10),
            'verification_attempts' => 0,
            'verified_at' => null,
            'reset_token_hash' => null,
            'reset_token_expires_at' => null,
            'consumed_at' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'resettable_type' => $user::class,
            'resettable_id' => $user->getKey(),
            'email_normalized' => $user->email,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'verified_at' => now(),
            'reset_token_hash' => hash('sha256', Str::uuid()->toString()),
            'reset_token_expires_at' => now()->addMinutes(10),
        ]);
    }

    public function consumed(): static
    {
        return $this->state(fn (): array => [
            'consumed_at' => now(),
        ]);
    }

    public function expiredCode(): static
    {
        return $this->state(fn (): array => [
            'code_expires_at' => now()->subMinute(),
        ]);
    }
}
