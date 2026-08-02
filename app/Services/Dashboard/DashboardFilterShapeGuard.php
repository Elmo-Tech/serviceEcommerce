<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

class DashboardFilterShapeGuard
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
        $seenNestedKeys = [];
        $allowedNestedKeys = ['ordersPeriod', 'dateFrom', 'dateTo', 'status'];

        foreach (explode('&', $queryString) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$rawKey, $rawValue] = array_pad(explode('=', $pair, 2), 2, null);

            $key = urldecode((string) $rawKey);
            $value = $rawValue === null ? null : urldecode($rawValue);

            if (! preg_match('/^filter\[([A-Za-z0-9_]+)\]$/', $key, $matches)) {
                $violations['payload'] = 'validation.invalid_payload';

                continue;
            }

            $nestedKey = $matches[1];

            if (! in_array($nestedKey, $allowedNestedKeys, true)) {
                $violations['payload'] = 'validation.invalid_payload';

                continue;
            }

            if (array_key_exists($nestedKey, $seenNestedKeys)) {
                $violations['filter.'.$nestedKey] = 'validation.invalid_payload';

                continue;
            }

            $seenNestedKeys[$nestedKey] = true;

            if ($value === null || trim($value) === '') {
                $violations['filter.'.$nestedKey] = 'validation.invalid_payload';
            }
        }

        return $violations;
    }
}
