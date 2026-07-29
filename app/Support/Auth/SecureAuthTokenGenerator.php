<?php

declare(strict_types=1);

namespace App\Support\Auth;

final class SecureAuthTokenGenerator
{
    public function generateRefreshToken(): string
    {
        return $this->generateUrlSafeToken(64);
    }

    public function generateResetToken(int $bytes = 32): string
    {
        return $this->generateUrlSafeToken(max(32, $bytes));
    }

    public function generateRecoveryCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function generateUrlSafeToken(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
