<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\RefreshTokenRevocationReason;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\Auth\AdminSessionRevocationService;
use App\Support\Auth\AuthenticationSecurityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ChangeAdminPasswordAction
{
    public function __construct(
        private readonly AdminSessionRevocationService $sessionRevocationService,
        private readonly AuthenticationSecurityLogger $securityLogger,
    ) {}

    /**
     * @return array{status: string}
     */
    public function execute(
        User $user,
        string $currentPassword,
        string $newPassword,
        ?string $ipAddress = null,
    ): array {
        if (! Hash::check($currentPassword, $user->password)) {
            return ['status' => 'current_password_invalid'];
        }

        DB::transaction(function () use ($user, $newPassword): void {
            $now = now();
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedUser->password = $newPassword;
            $lockedUser->save();

            $this->sessionRevocationService->revokeAllFor(
                $lockedUser,
                RefreshTokenRevocationReason::PASSWORD_CHANGED,
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
        });

        $this->securityLogger->info('admin_auth.password_changed', [
            'ip' => $ipAddress,
            'user' => $user,
        ]);

        return ['status' => 'success'];
    }
}
