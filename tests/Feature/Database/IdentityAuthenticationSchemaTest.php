<?php

declare(strict_types=1);

use App\Enums\UserType;
use App\Models\PasswordReset;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use App\Services\Auth\ResetTokenService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the identity authentication schema with the expected columns, casts, and relationships', function () {
    expect(Schema::hasColumns('users', [
        'name',
        'email',
        'password',
        'type',
        'is_active',
        'avatar_disk',
        'avatar_path',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('personal_access_tokens', [
            'tokenable_type',
            'tokenable_id',
            'name',
            'token',
            'abilities',
            'expires_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('refresh_tokens', [
            'tokenable_type',
            'tokenable_id',
            'token_hash',
            'family_id',
            'expires_at',
            'revoked_at',
            'rotated_to_token_id',
            'revocation_reason',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('password_resets', [
            'resettable_type',
            'resettable_id',
            'email_normalized',
            'code_hash',
            'code_expires_at',
            'verification_attempts',
            'verified_at',
            'reset_token_hash',
            'reset_token_expires_at',
            'consumed_at',
        ]))->toBeTrue();

    $user = User::factory()->administrator()->create();
    $refreshToken = RefreshToken::factory()->forUser($user)->create();
    $passwordReset = PasswordReset::factory()->forUser($user)->create();
    $accessToken = $user->createToken('admin-access-token', ['admin:access'], now()->addMinutes(15))->accessToken;

    expect($user->type)->toBe(UserType::ADMIN)
        ->and($user->is_active)->toBeTrue()
        ->and($refreshToken->tokenable->is($user))->toBeTrue()
        ->and($passwordReset->resettable->is($user))->toBeTrue()
        ->and($accessToken->tokenable->is($user))->toBeTrue()
        ->and($refreshToken->expires_at)->toBeInstanceOf(Carbon::class)
        ->and($passwordReset->code_expires_at)->toBeInstanceOf(Carbon::class)
        ->and($user->refreshTokens)->toHaveCount(1)
        ->and($user->passwordResets)->toHaveCount(1);
});

it('enforces unique email and secret-hash invariants', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.test',
    ]);

    expect(fn () => User::factory()->create([
        'email' => 'admin@example.test',
    ]))->toThrow(QueryException::class);

    $refreshHash = hash('sha256', 'refresh-secret');
    RefreshToken::factory()->forUser($user)->create([
        'token_hash' => $refreshHash,
    ]);

    expect(fn () => RefreshToken::factory()->forUser($user)->create([
        'token_hash' => $refreshHash,
    ]))->toThrow(QueryException::class);

    $resetHash = hash('sha256', 'reset-secret');
    PasswordReset::factory()->forUser($user)->verified()->create([
        'reset_token_hash' => $resetHash,
    ]);

    expect(fn () => PasswordReset::factory()->forUser($user)->verified()->create([
        'reset_token_hash' => $resetHash,
    ]))->toThrow(QueryException::class);
});

it('persists only hashed refresh, reset, and recovery secrets', function () {
    $user = User::factory()->create();
    $refreshTokenService = app(RefreshTokenService::class);
    $resetTokenService = app(ResetTokenService::class);

    $issuedRefreshToken = $refreshTokenService->issueFor($user);
    $passwordReset = PasswordReset::factory()->forUser($user)->create();
    $plainResetToken = $resetTokenService->generateToken();

    $resetTokenService->storeForWorkflow($passwordReset, $plainResetToken);
    $passwordReset->refresh();

    expect($issuedRefreshToken['token']->token_hash)->toBe(hash('sha256', $issuedRefreshToken['plainTextToken']))
        ->and($issuedRefreshToken['token']->token_hash)->not->toBe($issuedRefreshToken['plainTextToken'])
        ->and($passwordReset->reset_token_hash)->toBe(hash('sha256', $plainResetToken))
        ->and($passwordReset->reset_token_hash)->not->toBe($plainResetToken)
        ->and($passwordReset->code_hash)->not->toBe('123456')
        ->and(Hash::check('123456', $passwordReset->code_hash))->toBeTrue();
});
