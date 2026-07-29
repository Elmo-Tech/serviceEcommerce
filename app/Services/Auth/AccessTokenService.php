<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use DateTimeInterface;
use Laravel\Sanctum\NewAccessToken;

class AccessTokenService
{
    /**
     * @var list<string>
     */
    private const ABILITIES = ['admin:access'];

    private const TOKEN_NAME = 'admin-access-token';

    public function issueFor(User $user, ?DateTimeInterface $expiresAt = null): NewAccessToken
    {
        return $user->createToken(
            self::TOKEN_NAME,
            self::ABILITIES,
            $expiresAt ?? $this->expiresAt(),
        );
    }

    public function expiresAt(): DateTimeInterface
    {
        return now()->addMinutes($this->ttlMinutes());
    }

    public function ttlMinutes(): int
    {
        return (int) config('auth.access_token_ttl_minutes', 15);
    }

    public function ttlSeconds(): int
    {
        return $this->ttlMinutes() * 60;
    }

    public function revokeAllFor(User $user): int
    {
        return $user->tokens()->delete();
    }
}
