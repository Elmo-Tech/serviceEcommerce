<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\RefreshTokenRevocationReason;
use App\Models\User;
use Illuminate\Support\Carbon;

class AdminSessionRevocationService
{
    public function __construct(
        private readonly AccessTokenService $accessTokenService,
        private readonly RefreshTokenService $refreshTokenService,
    ) {}

    /**
     * @return array{accessTokensRevoked: int, refreshTokensRevoked: int}
     */
    public function revokeAllFor(
        User $user,
        RefreshTokenRevocationReason $reason,
        ?Carbon $now = null,
    ): array {
        $now ??= now();

        return [
            'accessTokensRevoked' => $this->accessTokenService->revokeAllFor($user),
            'refreshTokensRevoked' => $this->refreshTokenService->revokeActiveTokensFor($user, $reason, $now),
        ];
    }
}
