<?php

declare(strict_types=1);

namespace App\Services\HeroSlides;

class HeroSlideQueryShapeGuard
{
    /**
     * @return array<string, string>
     */
    public function violations(?string $queryString): array
    {
        if (! is_string($queryString) || trim($queryString) === '') {
            return [];
        }

        $violations = [];
        $seen = [];

        foreach (explode('&', $queryString) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$rawKey, $rawValue] = array_pad(explode('=', $pair, 2), 2, null);
            $key = urldecode((string) $rawKey);
            $value = $rawValue === null ? null : urldecode($rawValue);

            if (! in_array($key, ['page', 'perPage', 'filter[isActive]'], true)) {
                $violations['payload'] = 'validation.invalid_payload';

                continue;
            }

            if (isset($seen[$key])) {
                $violations[$this->attribute($key)] = 'validation.invalid_payload';

                continue;
            }

            $seen[$key] = true;

            if (! is_string($value) || ! $this->isCanonical($key, $value)) {
                $violations[$this->attribute($key)] = 'validation.invalid_payload';
            }
        }

        return $violations;
    }

    private function isCanonical(string $key, string $value): bool
    {
        if ($key === 'filter[isActive]') {
            return preg_match('/^[01]$/', $value) === 1;
        }

        return preg_match('/^[1-9][0-9]*$/', $value) === 1;
    }

    private function attribute(string $key): string
    {
        return $key === 'filter[isActive]' ? 'filter.isActive' : $key;
    }
}
