<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\PasswordReset;
use App\Models\User;
use App\Services\Auth\ForgotPasswordCodeService;
use App\Services\Auth\ResetTokenService;
use App\Support\Auth\AuthenticationSecurityLogger;
use Illuminate\Support\Facades\DB;

class VerifyForgotPasswordCodeAction
{
    public function __construct(
        private readonly ForgotPasswordCodeService $forgotPasswordCodeService,
        private readonly ResetTokenService $resetTokenService,
        private readonly AuthenticationSecurityLogger $securityLogger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $normalizedEmail, string $code, ?string $ipAddress = null): array
    {
        $user = User::query()
            ->where('email', $normalizedEmail)
            ->first();

        if (! $this->isEligibleAdministrator($user)) {
            return ['status' => 'invalid'];
        }

        return DB::transaction(function () use ($user, $normalizedEmail, $code, $ipAddress): array {
            $workflow = PasswordReset::query()
                ->whereMorphedTo('resettable', $user)
                ->where('email_normalized', $normalizedEmail)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $workflow instanceof PasswordReset) {
                return ['status' => 'invalid'];
            }

            if (! $this->forgotPasswordCodeService->verifyAgainstWorkflow($workflow, $code, now())) {
                $this->securityLogger->warning('admin_auth.password_reset_code_invalid', [
                    'ip' => $ipAddress,
                    'user' => $user,
                ]);

                return ['status' => 'invalid'];
            }

            $plainResetToken = $this->resetTokenService->generateToken();
            $workflow->markVerified(now());

            $workflow = $this->resetTokenService->storeForWorkflow($workflow, $plainResetToken, now());

            $this->securityLogger->info('admin_auth.password_reset_code_verified', [
                'ip' => $ipAddress,
                'user' => $user,
            ]);

            return [
                'status' => 'success',
                'resetToken' => $plainResetToken,
                'resetTokenExpiresIn' => $this->resetTokenService->ttlSeconds(),
                'workflow' => $workflow,
            ];
        });
    }

    private function isEligibleAdministrator(?User $user): bool
    {
        return $user instanceof User
            && $user->isAdministrator()
            && $user->hasActiveAccount()
            && $user->hasRole('super-admin');
    }
}
