<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RefreshTokenRevocationReason;
use Database\Factories\RefreshTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

class RefreshToken extends Model
{
    /** @use HasFactory<RefreshTokenFactory> */
    use HasFactory;

    protected $fillable = [
        'token_hash',
        'family_id',
        'expires_at',
        'revoked_at',
        'rotated_to_token_id',
        'revocation_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'revocation_reason' => RefreshTokenRevocationReason::class,
        ];
    }

    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    public function rotatedToToken(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rotated_to_token_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isRotated(): bool
    {
        return $this->rotated_to_token_id !== null;
    }

    public function hasValidFamilyId(): bool
    {
        return is_string($this->family_id) && trim($this->family_id) !== '';
    }

    public function hasValidRotationState(): bool
    {
        if (! $this->isRotated()) {
            return ! $this->isRevoked();
        }

        if (! $this->isRevoked()) {
            return false;
        }

        $successor = $this->rotatedToToken()->first();

        if (! $successor instanceof self) {
            return false;
        }

        return $successor->family_id === $this->family_id
            && $successor->tokenable_type === $this->tokenable_type
            && $successor->tokenable_id === $this->tokenable_id;
    }

    public function isExpired(?Carbon $now = null): bool
    {
        $now ??= now();

        return $this->expires_at->lessThanOrEqualTo($now);
    }

    public function isActive(?Carbon $now = null): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired($now);
    }
}
