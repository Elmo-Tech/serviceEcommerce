<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\RefreshTokenRevocationReason;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\AccessTokenService;
use App\Services\Auth\AdminSessionRevocationService;
use App\Services\Auth\RefreshTokenService;
use App\Support\Auth\AuthenticationSecurityLogger;
use Illuminate\Support\Facades\DB;

class RefreshAdminSessionAction
{
    public function __construct(
        private readonly AccessTokenService $accessTokenService,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly AdminSessionRevocationService $sessionRevocationService,
        private readonly AuthenticationSecurityLogger $securityLogger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(?string $plainTextToken, ?string $ipAddress = null): array
    {
        if (! is_string($plainTextToken) || $plainTextToken === '') {
            $this->logFailure('missing_refresh_token', $ipAddress);

            return ['status' => 'invalid'];
        }

        return DB::transaction(function () use ($plainTextToken, $ipAddress): array {
            $now = now();
            $refreshToken = RefreshToken::query()
                ->where('token_hash', $this->refreshTokenService->hashToken($plainTextToken))
                ->lockForUpdate()
                ->first();

            if (! $refreshToken instanceof RefreshToken) {
                $this->logFailure('unknown_refresh_token', $ipAddress);

                return ['status' => 'invalid'];
            }

            $user = $refreshToken->tokenable;

            if (! $user instanceof User || ! $user->isAdministrator() || ! $user->hasRole('super-admin')) {
                $this->logFailure('invalid_refresh_owner', $ipAddress, $user);

                return ['status' => 'invalid'];
            }

            if (! $user->hasActiveAccount()) {
                $this->sessionRevocationService->revokeAllFor(
                    $user,
                    RefreshTokenRevocationReason::USER_INACTIVE,
                    $now,
                );

                $this->logFailure('inactive_refresh_owner', $ipAddress, $user);

                return ['status' => 'invalid'];
            }

            if (! $refreshToken->hasValidFamilyId()) {
                $this->logFailure('invalid_refresh_family', $ipAddress, $user);

                return ['status' => 'invalid'];
            }

            if (! $refreshToken->hasValidRotationState()) {
                $this->logFailure('invalid_refresh_rotation', $ipAddress, $user);

                return ['status' => 'invalid'];
            }

            if ($refreshToken->isRevoked()) {
                if ($this->refreshTokenService->isReuseAttempt($refreshToken)) {
                    $this->sessionRevocationService->revokeAllFor(
                        $user,
                        RefreshTokenRevocationReason::REFRESH_REUSE_DETECTED,
                        $now,
                    );

                    $this->securityLogger->warning('admin_auth.refresh_reuse_detected', [
                        'ip' => $ipAddress,
                        'user' => $user,
                    ]);
                } else {
                    $this->logFailure('revoked_refresh_token', $ipAddress, $user);
                }

                return ['status' => 'invalid'];
            }

            if ($refreshToken->isExpired($now)) {
                $this->logFailure('expired_refresh_token', $ipAddress, $user);

                return ['status' => 'invalid'];
            }

            $this->refreshTokenService->cleanupExpiredFor($user, $now);
            $this->accessTokenService->revokeAllFor($user);

            $rotatedToken = $this->refreshTokenService->rotate($refreshToken, $now);
            $accessToken = $this->accessTokenService->issueFor(
                $user,
                $now->copy()->addMinutes($this->accessTokenService->ttlMinutes()),
            );

            $this->securityLogger->info('admin_auth.refresh_success', [
                'ip' => $ipAddress,
                'user' => $user,
            ]);

            return [
                'status' => 'success',
                'accessToken' => $accessToken->plainTextToken,
                'tokenType' => 'Bearer',
                'tokenExpiresIn' => $this->accessTokenService->ttlSeconds(),
                'refreshTokenExpiresIn' => $this->refreshTokenService->ttlSeconds(),
                'refreshToken' => $rotatedToken['plainTextToken'],
            ];
        });
    }

    private function logFailure(string $reason, ?string $ipAddress = null, ?User $user = null): void
    {
        $this->securityLogger->warning('admin_auth.refresh_failed', [
            'reason' => $reason,
            'ip' => $ipAddress,
            'user' => $user,
        ]);
    }
}
