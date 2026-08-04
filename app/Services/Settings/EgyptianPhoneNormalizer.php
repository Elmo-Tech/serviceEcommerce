<?php

declare(strict_types=1);

namespace App\Services\Settings;

class EgyptianPhoneNormalizer
{
    public function normalize(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', trim($phone)) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0020')) {
            $digits = '0'.substr($digits, 4);
        } elseif (str_starts_with($digits, '20')) {
            $digits = '0'.substr($digits, 2);
        }

        if (! preg_match('/^[0-9]{10,11}$/', $digits)) {
            return null;
        }

        return $digits;
    }
}
