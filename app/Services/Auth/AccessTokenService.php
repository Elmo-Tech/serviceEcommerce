<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

class AccessTokenService
{
    /**
     * @var list<string>
     */
    private const ABILITIES = ['admin:access'];

    private const TOKEN_NAME = 'admin-access-token';

    public function issueFor(User $user): NewAccessToken
    {
        return $user->createToken(
            self::TOKEN_NAME,
            self::ABILITIES,
            null,
        );
    }

    public function ttlSeconds(): ?int
    {
        return null;
    }

    public function revokeAllFor(User $user): int
    {
        return $user->tokens()->delete();
    }
}
