<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\RefreshTokenRevocationReason;
use App\Enums\UserType;
use App\Models\User;
use App\Services\Auth\AccessTokenService;
use App\Services\Auth\AdminSessionRevocationService;
use App\Services\Auth\RefreshTokenService;
use App\Support\Auth\AuthenticationSecurityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LoginAdminAction
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
    public function execute(string $email, string $password, ?string $ipAddress = null): array
    {
        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user instanceof User || ! Hash::check($password, $user->password)) {
            $this->logFailure('invalid_credentials', $email, $ipAddress);

            return ['status' => 'invalid_credentials'];
        }

        if ((int) $user->getRawOriginal('type') !== UserType::ADMIN->value) {
            $this->logFailure('non_administrator_type', $email, $ipAddress, $user);

            return ['status' => 'invalid_credentials'];
        }

        if (! $user->hasRole('super-admin')) {
            $this->logFailure('missing_super_admin_role', $email, $ipAddress, $user);

            return ['status' => 'invalid_credentials'];
        }

        if (! $user->hasActiveAccount()) {
            $this->logFailure('inactive_account', $email, $ipAddress, $user);

            return [
                'status' => 'inactive',
                'user' => $user,
            ];
        }

        $result = DB::transaction(function () use ($user): array {
            $now = now();

            $this->sessionRevocationService->revokeAllFor(
                $user,
                RefreshTokenRevocationReason::LOGIN_REPLACED,
                $now,
            );

            $this->refreshTokenService->cleanupExpiredFor($user, $now);

            $accessToken = $this->accessTokenService->issueFor(
                $user,
                $now->copy()->addMinutes($this->accessTokenService->ttlMinutes()),
            );

            $refreshToken = $this->refreshTokenService->issueFor($user, null, $now);

            return [
                'status' => 'success',
                'user' => $user->fresh(['roles', 'permissions']),
                'accessToken' => $accessToken->plainTextToken,
                'tokenType' => 'Bearer',
                'tokenExpiresIn' => $this->accessTokenService->ttlSeconds(),
                'refreshTokenExpiresIn' => $this->refreshTokenService->ttlSeconds(),
                'refreshToken' => $refreshToken['plainTextToken'],
            ];
        });

        $this->logSuccess($result['user'], $ipAddress);

        return $result;
    }

    private function logSuccess(User $user, ?string $ipAddress = null): void
    {
        $this->securityLogger->info('admin_auth.login_success', [
            'user' => $user,
            'ip' => $ipAddress,
        ]);
    }

    private function logFailure(
        string $reason,
        string $email,
        ?string $ipAddress = null,
        ?User $user = null,
    ): void {
        $this->securityLogger->warning('admin_auth.login_failed', [
            'reason' => $reason,
            'email' => $email,
            'user' => $user,
            'ip' => $ipAddress,
        ]);
    }
}
