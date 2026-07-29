<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\RefreshTokenRevocationReason;
use App\Models\RefreshToken;
use App\Models\User;
use App\Support\Auth\SecureAuthTokenGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RefreshTokenService
{
    public function __construct(
        private readonly SecureAuthTokenGenerator $tokenGenerator,
    ) {}

    /**
     * @return array{token: RefreshToken, plainTextToken: string}
     */
    public function issueFor(User $user, ?string $familyId = null, ?Carbon $now = null): array
    {
        $now ??= now();
        $plainTextToken = $this->generatePlainTextToken();

        $token = $user->refreshTokens()->create([
            'token_hash' => $this->hashToken($plainTextToken),
            'family_id' => $familyId ?? (string) Str::uuid(),
            'expires_at' => $now->copy()->addMinutes($this->ttlMinutes()),
        ]);

        return [
            'token' => $token,
            'plainTextToken' => $plainTextToken,
        ];
    }

    /**
     * @return array{token: RefreshToken, plainTextToken: string}
     */
    public function rotate(RefreshToken $refreshToken, ?Carbon $now = null): array
    {
        $now ??= now();

        $issued = $this->issueFor(
            $refreshToken->tokenable,
            $refreshToken->family_id,
            $now,
        );

        $refreshToken->forceFill([
            'revoked_at' => $now,
            'rotated_to_token_id' => $issued['token']->getKey(),
            'revocation_reason' => RefreshTokenRevocationReason::REFRESHED,
        ])->save();

        return $issued;
    }

    public function findByPlainTextToken(string $plainTextToken): ?RefreshToken
    {
        return RefreshToken::query()
            ->where('token_hash', $this->hashToken($plainTextToken))
            ->first();
    }

    public function hashToken(string $plainTextToken): string
    {
        return hash('sha256', $plainTextToken);
    }

    public function generatePlainTextToken(): string
    {
        return $this->tokenGenerator->generateRefreshToken();
    }

    public function revoke(
        RefreshToken $refreshToken,
        RefreshTokenRevocationReason $reason,
        ?Carbon $now = null,
    ): bool {
        if ($refreshToken->isRevoked()) {
            return false;
        }

        $now ??= now();

        return $refreshToken->forceFill([
            'revoked_at' => $now,
            'revocation_reason' => $reason,
        ])->save();
    }

    public function revokeActiveTokensFor(
        User $user,
        RefreshTokenRevocationReason $reason,
        ?Carbon $now = null,
    ): int {
        $now ??= now();

        return $user->refreshTokens()
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => $now,
                'revocation_reason' => $reason,
                'updated_at' => $now,
            ]);
    }

    public function cleanupExpiredFor(User $user, ?Carbon $now = null): int
    {
        $now ??= now();

        return $user->refreshTokens()
            ->where('expires_at', '<=', $now)
            ->delete();
    }

    public function isReuseAttempt(RefreshToken $refreshToken): bool
    {
        return $refreshToken->isRevoked()
            && $refreshToken->isRotated()
            && $refreshToken->hasValidRotationState();
    }

    public function recentlyIssuedFamilyTokens(User $user, string $familyId): Collection
    {
        return $user->refreshTokens()
            ->where('family_id', $familyId)
            ->orderByDesc('id')
            ->get();
    }

    public function ttlMinutes(): int
    {
        return (int) config('auth.refresh_token_ttl_minutes', 43200);
    }

    public function ttlSeconds(): int
    {
        return $this->ttlMinutes() * 60;
    }
}
