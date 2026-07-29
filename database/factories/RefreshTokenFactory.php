<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RefreshTokenRevocationReason;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<RefreshToken>
 */
class RefreshTokenFactory extends Factory
{
    protected $model = RefreshToken::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tokenable_type' => User::class,
            'tokenable_id' => 1,
            'token_hash' => hash('sha256', Str::uuid()->toString()),
            'family_id' => (string) Str::uuid(),
            'expires_at' => now()->addDays(30),
            'revoked_at' => null,
            'rotated_to_token_id' => null,
            'revocation_reason' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'tokenable_type' => $user::class,
            'tokenable_id' => $user->getKey(),
        ]);
    }

    public function active(?Carbon $now = null): static
    {
        $now ??= now();

        return $this->state(fn (): array => [
            'expires_at' => $now->copy()->addDays(30),
            'revoked_at' => null,
            'rotated_to_token_id' => null,
            'revocation_reason' => null,
        ]);
    }

    public function expired(?Carbon $now = null): static
    {
        $now ??= now();

        return $this->state(fn (): array => [
            'expires_at' => $now->copy()->subMinute(),
        ]);
    }

    public function revoked(
        RefreshTokenRevocationReason $reason = RefreshTokenRevocationReason::LOGOUT,
        ?Carbon $now = null,
    ): static {
        $now ??= now();

        return $this->state(fn (): array => [
            'revoked_at' => $now,
            'revocation_reason' => $reason,
        ]);
    }
}
