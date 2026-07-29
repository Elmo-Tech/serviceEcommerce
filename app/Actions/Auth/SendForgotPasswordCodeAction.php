<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Mail\AdminPasswordResetCodeMail;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\Auth\ForgotPasswordCodeService;
use App\Support\Auth\AuthenticationSecurityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendForgotPasswordCodeAction
{
    public function __construct(
        private readonly ForgotPasswordCodeService $forgotPasswordCodeService,
        private readonly AuthenticationSecurityLogger $securityLogger,
    ) {}

    /**
     * @return array{status: string}
     */
    public function execute(string $normalizedEmail, ?string $ipAddress = null): array
    {
        $user = User::query()
            ->where('email', $normalizedEmail)
            ->first();

        if (! $this->isEligibleAdministrator($user)) {
            return ['status' => 'accepted'];
        }

        $result = DB::transaction(function () use ($user, $normalizedEmail) {
            $now = now();
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->first();

            if (! $this->isEligibleAdministrator($lockedUser)) {
                return ['status' => 'accepted'];
            }

            $latestWorkflow = PasswordReset::query()
                ->whereMorphedTo('resettable', $lockedUser)
                ->where('email_normalized', $normalizedEmail)
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $cooldownSeconds = (int) config('auth.password_reset_resend_cooldown_seconds', 60);

            if (
                $latestWorkflow instanceof PasswordReset
                && $latestWorkflow->created_at !== null
                && $latestWorkflow->created_at->greaterThan($now->copy()->subSeconds($cooldownSeconds))
            ) {
                return ['status' => 'rate_limited'];
            }

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

            $plainCode = $this->forgotPasswordCodeService->generateCode();

            $workflow = $lockedUser->passwordResets()->create([
                'email_normalized' => $normalizedEmail,
                'code_hash' => $this->forgotPasswordCodeService->hashCode($plainCode),
                'code_expires_at' => $this->forgotPasswordCodeService->codeExpiresAt($now),
                'verification_attempts' => 0,
                'verified_at' => null,
                'reset_token_hash' => null,
                'reset_token_expires_at' => null,
                'consumed_at' => null,
            ]);

            return [
                'status' => 'created',
                'code' => $plainCode,
                'user' => $lockedUser,
                'workflowId' => $workflow->getKey(),
            ];
        });

        if ($result['status'] === 'accepted' || $result['status'] === 'rate_limited') {
            return ['status' => $result['status']];
        }

        try {
            Mail::to($user->email)->send(new AdminPasswordResetCodeMail(
                (string) $result['code'],
                $this->forgotPasswordCodeService->ttlMinutes(),
            ));

            $this->securityLogger->info('admin_auth.password_reset_requested', [
                'ip' => $ipAddress,
                'user' => $result['user'],
            ]);

            return ['status' => 'accepted'];
        } catch (Throwable $throwable) {
            PasswordReset::query()
                ->whereKey($result['workflowId'])
                ->update([
                    'consumed_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->securityLogger->error('admin_auth.password_reset_mail_failed', [
                'exception' => $throwable,
                'ip' => $ipAddress,
                'user' => $result['user'],
            ]);

            return ['status' => 'mail_unavailable'];
        }
    }

    private function isEligibleAdministrator(?User $user): bool
    {
        return $user instanceof User
            && $user->isAdministrator()
            && $user->hasActiveAccount()
            && $user->hasRole('super-admin');
    }
}
