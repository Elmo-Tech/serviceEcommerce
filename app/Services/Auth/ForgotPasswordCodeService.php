<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\PasswordReset;
use App\Support\Auth\SecureAuthTokenGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ForgotPasswordCodeService
{
    public function __construct(
        private readonly SecureAuthTokenGenerator $tokenGenerator,
    ) {}

    public function generateCode(): string
    {
        return $this->tokenGenerator->generateRecoveryCode();
    }

    public function hashCode(string $plainCode): string
    {
        return Hash::make($plainCode);
    }

    public function codeExpiresAt(?Carbon $now = null): Carbon
    {
        $now ??= now();

        return $now->copy()->addMinutes($this->ttlMinutes());
    }

    public function ttlMinutes(): int
    {
        return (int) config('auth.password_reset_code_ttl_minutes', 10);
    }

    public function ttlSeconds(): int
    {
        return $this->ttlMinutes() * 60;
    }

    public function maxAttempts(): int
    {
        return (int) config('auth.password_reset_max_attempts', 5);
    }

    public function verifyAgainstWorkflow(
        PasswordReset $passwordReset,
        string $plainCode,
        ?Carbon $now = null,
    ): bool {
        $now ??= now();

        if (! $this->canVerifyWorkflow($passwordReset, $now)) {
            return false;
        }

        if (Hash::check($plainCode, $passwordReset->code_hash)) {
            return true;
        }

        $this->recordFailedAttempt($passwordReset, $now);

        return false;
    }

    public function canVerifyWorkflow(PasswordReset $passwordReset, ?Carbon $now = null): bool
    {
        $now ??= now();

        return $passwordReset->canAttemptVerification($this->maxAttempts(), $now);
    }

    public function recordFailedAttempt(PasswordReset $passwordReset, ?Carbon $now = null): PasswordReset
    {
        $now ??= now();
        $maxAttempts = $this->maxAttempts();
        $timestamp = $now->toDateTimeString();

        PasswordReset::query()
            ->whereKey($passwordReset->getKey())
            ->update([
                'verification_attempts' => DB::raw('LEAST(verification_attempts + 1, '.$maxAttempts.')'),
                'consumed_at' => DB::raw(
                    "CASE WHEN verification_attempts + 1 >= {$maxAttempts} THEN COALESCE(consumed_at, '{$timestamp}') ELSE consumed_at END"
                ),
                'updated_at' => $now,
            ]);

        return $passwordReset->refresh();
    }
}
