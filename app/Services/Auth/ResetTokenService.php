<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\PasswordReset;
use App\Support\Auth\SecureAuthTokenGenerator;
use Illuminate\Support\Carbon;

class ResetTokenService
{
    public function __construct(
        private readonly SecureAuthTokenGenerator $tokenGenerator,
    ) {}

    public function generateToken(): string
    {
        return $this->tokenGenerator->generateResetToken();
    }

    public function hashToken(string $plainTextToken): string
    {
        return hash('sha256', $plainTextToken);
    }

    public function expiresAt(?Carbon $now = null): Carbon
    {
        $now ??= now();

        return $now->copy()->addMinutes($this->ttlMinutes());
    }

    public function ttlMinutes(): int
    {
        return (int) config('auth.password_reset_token_ttl_minutes', 10);
    }

    public function ttlSeconds(): int
    {
        return $this->ttlMinutes() * 60;
    }

    public function findByPlainTextToken(string $plainTextToken): ?PasswordReset
    {
        return PasswordReset::query()
            ->where('reset_token_hash', $this->hashToken($plainTextToken))
            ->first();
    }

    public function matchesWorkflow(
        PasswordReset $passwordReset,
        string $plainTextToken,
        ?Carbon $now = null,
    ): bool {
        $now ??= now();

        return $passwordReset->hasUsableResetToken($now)
            && hash_equals($passwordReset->reset_token_hash, $this->hashToken($plainTextToken));
    }

    public function storeForWorkflow(
        PasswordReset $passwordReset,
        string $plainTextToken,
        ?Carbon $now = null,
    ): PasswordReset {
        $passwordReset->forceFill([
            'reset_token_hash' => $this->hashToken($plainTextToken),
            'reset_token_expires_at' => $this->expiresAt($now),
        ])->save();

        return $passwordReset->refresh();
    }
}
