<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

class DashboardMoneyResultNormalizer
{
    public function format(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    /**
     * @return array<string, string>
     */
    public function financialBlock(object|array $result): array
    {
        return [
            'total' => $this->format(data_get($result, 'total')),
            'today' => $this->format(data_get($result, 'today')),
            'period' => $this->format(data_get($result, 'period')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function uncollectedBlock(object|array $result): array
    {
        return [
            'total' => $this->format(data_get($result, 'total')),
            'today' => $this->format(data_get($result, 'today')),
        ];
    }
}
