<?php

declare(strict_types=1);

use App\Enums\RefreshTokenRevocationReason;
use App\Models\PasswordReset;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\AccessTokenService;
use App\Services\Auth\ForgotPasswordCodeService;
use App\Services\Auth\RefreshTokenService;
use App\Services\Auth\ResetTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

it('issues non-expiring sanctum access tokens with the approved ability', function () {
    Carbon::setTestNow('2026-07-28 12:00:00');

    $user = User::factory()->create();
    $service = app(AccessTokenService::class);

    $issuedToken = $service->issueFor($user);
    $persistedToken = $issuedToken->accessToken->fresh();

    expect($issuedToken->plainTextToken)->toContain('|')
        ->and($persistedToken)->not->toBeNull()
        ->and($persistedToken->name)->toBe('admin-access-token')
        ->and($persistedToken->abilities)->toBe(['admin:access'])
        ->and($persistedToken->expires_at)->toBeNull()
        ->and($service->ttlSeconds())->toBeNull();

    expect($service->revokeAllFor($user))->toBe(1)
        ->and($user->tokens()->count())->toBe(0);
});

it('issues, rotates, revokes, and cleans up refresh tokens with scoped behavior', function () {
    Carbon::setTestNow('2026-07-28 13:00:00');

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $service = app(RefreshTokenService::class);

    $issuedToken = $service->issueFor($user);
    $originalToken = $issuedToken['token']->fresh();

    expect($originalToken)->not->toBeNull()
        ->and($originalToken->token_hash)->toBe(hash('sha256', $issuedToken['plainTextToken']))
        ->and($service->findByPlainTextToken($issuedToken['plainTextToken'])?->is($originalToken))->toBeTrue()
        ->and($originalToken->expires_at->timestamp)->toBe(now()->addDays(30)->timestamp)
        ->and($service->ttlMinutes())->toBe(43200)
        ->and($service->ttlSeconds())->toBe(2592000);

    $rotated = $service->rotate($originalToken, now());
    $successorToken = $rotated['token']->fresh();
    $originalToken->refresh();

    expect($successorToken)->not->toBeNull()
        ->and($successorToken->family_id)->toBe($originalToken->family_id)
        ->and($originalToken->rotated_to_token_id)->toBe($successorToken->getKey())
        ->and($originalToken->revocation_reason)->toBe(RefreshTokenRevocationReason::REFRESHED)
        ->and($service->isReuseAttempt($originalToken))->toBeTrue();

    $expiredForUser = RefreshToken::factory()->forUser($user)->expired(now())->create();
    $expiredForOtherUser = RefreshToken::factory()->forUser($otherUser)->expired(now())->create();

    expect($service->cleanupExpiredFor($user, now()))->toBe(1)
        ->and(RefreshToken::find($expiredForUser->getKey()))->toBeNull()
        ->and(RefreshToken::find($expiredForOtherUser->getKey()))->not->toBeNull();

    $activeOne = RefreshToken::factory()->forUser($user)->active(now())->create();
    $activeTwo = RefreshToken::factory()->forUser($user)->active(now())->create();

    expect($service->revokeActiveTokensFor($user, RefreshTokenRevocationReason::LOGOUT, now()))->toBe(3);

    expect($activeOne->fresh()?->revocation_reason)->toBe(RefreshTokenRevocationReason::LOGOUT)
        ->and($activeTwo->fresh()?->revocation_reason)->toBe(RefreshTokenRevocationReason::LOGOUT)
        ->and($successorToken->fresh()?->revocation_reason)->toBe(RefreshTokenRevocationReason::LOGOUT);
});

it('generates url-safe refresh tokens with 64 bytes of entropy before hashing', function () {
    $service = app(RefreshTokenService::class);
    $plainTextToken = $service->generatePlainTextToken();
    $base64 = strtr($plainTextToken, '-_', '+/');
    $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
    $decoded = base64_decode($base64, true);

    expect($plainTextToken)->toMatch('/^[A-Za-z0-9_-]+$/')
        ->and($decoded)->toBeString()
        ->and(strlen((string) $decoded))->toBe(64)
        ->and($service->hashToken($plainTextToken))->toBe(hash('sha256', $plainTextToken));
});

it('generates, hashes, verifies, and attempt-limits password recovery codes', function () {
    Carbon::setTestNow('2026-07-28 14:00:00');

    $user = User::factory()->create();
    $service = app(ForgotPasswordCodeService::class);
    $generatedCode = $service->generateCode();
    $passwordReset = PasswordReset::factory()->forUser($user)->create([
        'code_hash' => $service->hashCode('654321'),
        'code_expires_at' => now()->addMinutes(10),
    ]);

    expect($generatedCode)->toMatch('/^\d{6}$/')
        ->and($service->ttlMinutes())->toBe(10)
        ->and($service->ttlSeconds())->toBe(600)
        ->and($service->maxAttempts())->toBe(5)
        ->and($service->verifyAgainstWorkflow($passwordReset, '654321', now()))->toBeTrue();

    $limitedReset = PasswordReset::factory()->forUser($user)->create([
        'code_hash' => $service->hashCode('123456'),
        'code_expires_at' => now()->addMinutes(10),
    ]);

    expect($service->verifyAgainstWorkflow($limitedReset, '000000', now()))->toBeFalse()
        ->and($limitedReset->fresh()?->verification_attempts)->toBe(1);

    $fifthAttemptReset = PasswordReset::factory()->forUser($user)->create([
        'code_hash' => $service->hashCode('222222'),
        'code_expires_at' => now()->addMinutes(10),
        'verification_attempts' => 4,
    ]);

    expect($service->verifyAgainstWorkflow($fifthAttemptReset, '000000', now()))->toBeFalse()
        ->and($fifthAttemptReset->fresh()?->verification_attempts)->toBe(5)
        ->and($fifthAttemptReset->fresh()?->consumed_at)->not->toBeNull()
        ->and($service->verifyAgainstWorkflow($fifthAttemptReset->fresh(), '222222', now()))->toBeFalse();
});

it('stores, finds, and validates reset tokens with the approved exact lifetime', function () {
    Carbon::setTestNow('2026-07-28 15:00:00');

    $service = app(ResetTokenService::class);
    $passwordReset = PasswordReset::factory()->create();
    $plainResetToken = $service->generateToken();

    $passwordReset->forceFill([
        'verified_at' => now(),
    ])->save();

    $service->storeForWorkflow($passwordReset, $plainResetToken, now());
    $passwordReset->refresh();

    expect($plainResetToken)->not->toBe('')
        ->toMatch('/^[A-Za-z0-9_-]+$/')
        ->and($service->ttlMinutes())->toBe(10)
        ->and($service->ttlSeconds())->toBe(600)
        ->and($passwordReset->reset_token_hash)->toBe(hash('sha256', $plainResetToken))
        ->and($passwordReset->reset_token_expires_at?->timestamp)->toBe(now()->addMinutes(10)->timestamp)
        ->and($service->findByPlainTextToken($plainResetToken)?->is($passwordReset))->toBeTrue()
        ->and($service->matchesWorkflow($passwordReset, $plainResetToken, now()))->toBeTrue();

    Carbon::setTestNow(now()->addMinutes(11));

    expect($service->matchesWorkflow($passwordReset->fresh(), $plainResetToken, now()))->toBeFalse();
});

it('tracks password reset workflow helpers and one-time consumption behavior', function () {
    Carbon::setTestNow('2026-07-28 16:00:00');

    $workflow = PasswordReset::factory()->create([
        'code_expires_at' => now()->addMinutes(10),
        'verification_attempts' => 0,
    ]);

    expect($workflow->canAttemptVerification(5, now()))->toBeTrue()
        ->and($workflow->hasRemainingVerificationAttempts(5))->toBeTrue()
        ->and($workflow->hasUsableResetToken(now()))->toBeFalse();

    $workflow->markVerified(now());
    $workflow->forceFill([
        'reset_token_hash' => hash('sha256', 'reset-token'),
        'reset_token_expires_at' => now()->addMinutes(10),
    ])->save();

    expect($workflow->fresh()->isVerified())->toBeTrue()
        ->and($workflow->fresh()->hasUsableResetToken(now()))->toBeTrue();

    $workflow->refresh()->consume(now());

    expect($workflow->fresh()->isConsumed())->toBeTrue()
        ->and($workflow->fresh()->canAttemptVerification(5, now()))->toBeFalse()
        ->and($workflow->fresh()->hasUsableResetToken(now()))->toBeFalse();
});
