<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\RefreshTokenRevocationReason;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\Auth\AdminSessionRevocationService;
use App\Services\Auth\ResetTokenService;
use App\Support\Auth\AuthenticationSecurityLogger;
use Illuminate\Support\Facades\DB;

class ResetForgottenPasswordAction
{
    public function __construct(
        private readonly ResetTokenService $resetTokenService,
        private readonly AdminSessionRevocationService $sessionRevocationService,
        private readonly AuthenticationSecurityLogger $securityLogger,
    ) {}

    /**
     * @return array{status: string}
     */
    public function execute(string $normalizedEmail, string $plainResetToken, string $password, ?string $ipAddress = null): array
    {
        $user = User::query()
            ->where('email', $normalizedEmail)
            ->first();

        if (! $this->isEligibleAdministrator($user)) {
            return ['status' => 'invalid'];
        }

        $status = DB::transaction(function () use ($user, $normalizedEmail, $plainResetToken, $password): array {
            $now = now();
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->first();

            if (! $this->isEligibleAdministrator($lockedUser)) {
                return ['status' => 'invalid'];
            }

            $workflow = PasswordReset::query()
                ->whereMorphedTo('resettable', $lockedUser)
                ->where('email_normalized', $normalizedEmail)
                ->where('reset_token_hash', $this->resetTokenService->hashToken($plainResetToken))
                ->lockForUpdate()
                ->first();

            if (! $workflow instanceof PasswordReset || ! $this->resetTokenService->matchesWorkflow($workflow, $plainResetToken, $now)) {
                return ['status' => 'invalid'];
            }

            $lockedUser->password = $password;
            $lockedUser->save();

            $this->sessionRevocationService->revokeAllFor(
                $lockedUser,
                RefreshTokenRevocationReason::PASSWORD_RESET,
                $now,
            );

            PasswordReset::query()
                ->whereMorphedTo('resettable', $lockedUser)
                ->whereNull('consumed_at')
                ->where(function ($query) use ($now): void {
                    $query->where('code_expires_at', '>', $now)
                        ->orWhere(function ($resetQuery) use ($now): void {
                            $resetQuery->whereNotNull('reset_token_expires_at')
                                ->where('reset_token_expires_at', '>', $now);
                        });
                })
                ->update([
                    'consumed_at' => $now,
                    'updated_at' => $now,
                ]);

            return ['status' => 'success'];
        });

        if ($status['status'] === 'success') {
            $this->securityLogger->info('admin_auth.password_reset_completed', [
                'ip' => $ipAddress,
                'user' => $user,
            ]);
        }

        return $status;
    }

    private function isEligibleAdministrator(?User $user): bool
    {
        return $user instanceof User
            && $user->isAdministrator()
            && $user->hasActiveAccount()
            && $user->hasRole('super-admin');
    }
}
