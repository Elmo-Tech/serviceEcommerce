<?php

declare(strict_types=1);

namespace App\Services\Customers;

class AddressNormalizationService
{
    public function normalizeOptionalText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = $this->collapseWhitespace($value);

        return $normalized === '' ? null : $normalized;
    }

    public function normalizeRequiredText(string $value): string
    {
        return $this->collapseWhitespace($value);
    }

    /**
     * @return array{province:string,city:string,address:string,addressHash:string}
     */
    public function normalizeIdentity(string $province, string $city, string $address): array
    {
        $normalizedProvince = $this->normalizeRequiredText($province);
        $normalizedCity = $this->normalizeRequiredText($city);
        $normalizedAddress = $this->normalizeRequiredText($address);

        return [
            'province' => $normalizedProvince,
            'city' => $normalizedCity,
            'address' => $normalizedAddress,
            'addressHash' => hash('sha256', implode('|', [
                mb_strtolower($normalizedProvince),
                mb_strtolower($normalizedCity),
                mb_strtolower($normalizedAddress),
            ])),
        ];
    }

    private function collapseWhitespace(string $value): string
    {
        $trimmed = trim($value);

        return preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;
    }
}
