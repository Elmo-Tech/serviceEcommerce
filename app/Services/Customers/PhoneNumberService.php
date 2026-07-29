<?php

declare(strict_types=1);

namespace App\Services\Customers;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneNumberService
{
    private PhoneNumberUtil $phoneNumberUtil;

    public function __construct()
    {
        $this->phoneNumberUtil = PhoneNumberUtil::getInstance();
    }

    /**
     * @return array{display: string, normalized: string, regionCode: string}|null
     */
    public function normalize(string $phone, ?string $defaultRegion = 'EG'): ?array
    {
        $candidate = trim($phone);

        if ($candidate === '') {
            return null;
        }

        $regionCode = strtoupper(trim((string) ($defaultRegion ?: 'EG')));

        if (! preg_match('/^[A-Z]{2}$/', $regionCode)) {
            $regionCode = 'EG';
        }

        try {
            $number = $this->phoneNumberUtil->parse($candidate, $regionCode);
        } catch (NumberParseException) {
            return null;
        }

        if (! $this->phoneNumberUtil->isValidNumber($number)) {
            return null;
        }

        return [
            'display' => $this->phoneNumberUtil->format($number, PhoneNumberFormat::INTERNATIONAL),
            'normalized' => $this->phoneNumberUtil->format($number, PhoneNumberFormat::E164),
            'regionCode' => (string) ($this->phoneNumberUtil->getRegionCodeForNumber($number) ?: $regionCode),
        ];
    }
}
