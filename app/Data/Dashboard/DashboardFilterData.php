<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

readonly class DashboardFilterData
{
    public function __construct(
        public string $ordersPeriod = 'today',
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?int $status = null,
    ) {}

    public function hasDatePair(): bool
    {
        return $this->dateFrom !== null && $this->dateTo !== null;
    }

    public function usesCustomOrdersPeriod(): bool
    {
        return $this->ordersPeriod === 'custom';
    }
}
