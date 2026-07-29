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

    public function normalizeCountryCode(string $countryCode): string
    {
        return strtoupper(trim($countryCode));
    }

    /**
     * @return array{
     *     countryCode: string,
     *     city: string,
     *     area: ?string,
     *     street: string,
     *     addressHash: string
     * }
     */
    public function normalizeIdentity(
        string $countryCode,
        string $city,
        ?string $area,
        string $street,
    ): array {
        $normalizedCountryCode = $this->normalizeCountryCode($countryCode);
        $normalizedCity = $this->normalizeRequiredText($city);
        $normalizedArea = $this->normalizeOptionalText($area);
        $normalizedStreet = $this->normalizeRequiredText($street);

        return [
            'countryCode' => $normalizedCountryCode,
            'city' => $normalizedCity,
            'area' => $normalizedArea,
            'street' => $normalizedStreet,
            'addressHash' => hash('sha256', implode('|', [
                mb_strtolower($normalizedCountryCode),
                mb_strtolower($normalizedCity),
                mb_strtolower($normalizedArea ?? ''),
                mb_strtolower($normalizedStreet),
            ])),
        ];
    }

    private function collapseWhitespace(string $value): string
    {
        $trimmed = trim($value);

        return preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;
    }
}
