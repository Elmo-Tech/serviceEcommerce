<?php

declare(strict_types=1);

namespace App\Support\Customers;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

final class CustomerPhoneFormatter
{
    public static function forResponse(?string $displayPhone, ?string $normalizedPhone): ?string
    {
        $fallbackDisplay = is_string($displayPhone) ? trim($displayPhone) : null;

        if ($fallbackDisplay === null || $fallbackDisplay === '') {
            return null;
        }

        $normalized = is_string($normalizedPhone) ? trim($normalizedPhone) : '';

        if ($normalized === '') {
            return $fallbackDisplay;
        }

        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        try {
            $number = $phoneNumberUtil->parse($normalized, 'EG');
        } catch (NumberParseException) {
            return $fallbackDisplay;
        }

        if (($phoneNumberUtil->getRegionCodeForNumber($number) ?: null) !== 'EG') {
            return $fallbackDisplay;
        }

        $national = $phoneNumberUtil->format($number, PhoneNumberFormat::NATIONAL);
        $digitsOnly = preg_replace('/\D+/', '', $national);

        return is_string($digitsOnly) && $digitsOnly !== ''
            ? $digitsOnly
            : $fallbackDisplay;
    }
}
