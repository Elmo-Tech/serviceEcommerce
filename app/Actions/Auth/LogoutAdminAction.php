<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\RefreshTokenRevocationReason;
use App\Models\User;
use App\Services\Auth\AdminSessionRevocationService;
use App\Support\Auth\AuthenticationSecurityLogger;

class LogoutAdminAction
{
    public function __construct(
        private readonly AdminSessionRevocationService $sessionRevocationService,
        private readonly AuthenticationSecurityLogger $securityLogger,
    ) {}

    public function execute(User $user, ?string $ipAddress = null): void
    {
        $this->sessionRevocationService->revokeAllFor(
            $user,
            RefreshTokenRevocationReason::LOGOUT,
        );

        $this->securityLogger->info('admin_auth.logout', [
            'ip' => $ipAddress,
            'user' => $user,
        ]);
    }
}
