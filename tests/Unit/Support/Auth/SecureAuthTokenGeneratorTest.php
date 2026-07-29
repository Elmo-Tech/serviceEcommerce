<?php

declare(strict_types=1);

use App\Support\Auth\SecureAuthTokenGenerator;

it('generates approved refresh tokens, reset tokens, and six-digit recovery codes', function () {
    $generator = app(SecureAuthTokenGenerator::class);

    $refreshToken = $generator->generateRefreshToken();
    $resetToken = $generator->generateResetToken();
    $recoveryCode = $generator->generateRecoveryCode();

    $refreshBase64 = strtr($refreshToken, '-_', '+/');
    $refreshBase64 .= str_repeat('=', (4 - strlen($refreshBase64) % 4) % 4);
    $decodedRefreshToken = base64_decode($refreshBase64, true);

    $resetBase64 = strtr($resetToken, '-_', '+/');
    $resetBase64 .= str_repeat('=', (4 - strlen($resetBase64) % 4) % 4);
    $decodedResetToken = base64_decode($resetBase64, true);

    expect($refreshToken)->toMatch('/^[A-Za-z0-9_-]+$/')
        ->and($decodedRefreshToken)->toBeString()
        ->and(strlen((string) $decodedRefreshToken))->toBe(64)
        ->and($resetToken)->toMatch('/^[A-Za-z0-9_-]+$/')
        ->and($decodedResetToken)->toBeString()
        ->and(strlen((string) $decodedResetToken))->toBeGreaterThanOrEqual(32)
        ->and($recoveryCode)->toMatch('/^\d{6}$/');
});

it('avoids forbidden predictable generator implementations', function () {
    $source = file_get_contents(app_path('Support/Auth/SecureAuthTokenGenerator.php'));

    expect($source)->toBeString()
        ->not->toContain('mt_rand(')
        ->not->toContain('rand(')
        ->not->toContain('uniqid(')
        ->not->toContain('microtime(')
        ->not->toContain('Str::uuid')
        ->not->toContain('uuid_create')
        ->not->toContain('time(');
});
