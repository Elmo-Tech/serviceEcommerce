<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PasswordResetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

class PasswordReset extends Model
{
    /** @use HasFactory<PasswordResetFactory> */
    use HasFactory;

    protected $fillable = [
        'email_normalized',
        'code_hash',
        'code_expires_at',
        'verification_attempts',
        'verified_at',
        'reset_token_hash',
        'reset_token_expires_at',
        'consumed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'reset_token_expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'verification_attempts' => 'int',
        ];
    }

    public function resettable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function codeIsExpired(?Carbon $now = null): bool
    {
        $now ??= now();

        if ($this->code_expires_at === null) {
            return true;
        }

        return $this->code_expires_at->lessThanOrEqualTo($now);
    }

    public function resetTokenIsExpired(?Carbon $now = null): bool
    {
        $now ??= now();

        if ($this->reset_token_expires_at === null) {
            return true;
        }

        return $this->reset_token_expires_at->lessThanOrEqualTo($now);
    }

    public function hasRemainingVerificationAttempts(int $maxAttempts = 5): bool
    {
        return $this->verification_attempts < $maxAttempts;
    }

    public function canAttemptVerification(int $maxAttempts = 5, ?Carbon $now = null): bool
    {
        $now ??= now();

        return ! $this->isConsumed()
            && ! $this->isVerified()
            && ! $this->codeIsExpired($now)
            && $this->hasRemainingVerificationAttempts($maxAttempts);
    }

    public function hasUsableResetToken(?Carbon $now = null): bool
    {
        $now ??= now();

        return ! $this->isConsumed()
            && $this->isVerified()
            && $this->reset_token_hash !== null
            && ! $this->resetTokenIsExpired($now);
    }

    public function markVerified(?Carbon $now = null): self
    {
        $now ??= now();

        $this->forceFill([
            'verified_at' => $this->verified_at ?? $now,
        ])->save();

        return $this;
    }

    public function consume(?Carbon $now = null): self
    {
        if ($this->isConsumed()) {
            return $this;
        }

        $now ??= now();

        $this->forceFill([
            'consumed_at' => $now,
        ])->save();

        return $this;
    }
}
